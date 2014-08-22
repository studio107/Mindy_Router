<?php

namespace Mindy\Router;

use Mindy\Router\Exception\HttpMethodNotAllowedException;

class Dispatcher
{
    /**
     * @var RouteCollector
     */
    public $collector;
    /**
     * @var
     */
    public $matchedRoute;
    /**
     * @var
     */
    private $staticRouteMap;
    /**
     * @var
     */
    private $variableRouteData;

    public function __construct(RouteCollector $collector)
    {
        $this->collector = $collector;
        list($this->staticRouteMap, $this->variableRouteData) = $collector->getData();
    }

    public function reverse($name, $args = [])
    {
        return $this->collector->reverse($name, $args);
    }

    public function dispatch($httpMethod, $uri)
    {
        $uri = strtok($uri, '?');
        $uri = ltrim($uri, '/');
        $data = $this->dispatchRoute($httpMethod, $uri);
        if ($data === false) {
            return false;
        }

        return $this->getResponse($data);
    }

    public function getResponse($data)
    {
        list($handler, $vars) = $data;
        return call_user_func_array($this->resolveHandler($handler), $vars);
    }

    public function resolveHandler($handler)
    {
        if (is_array($handler) and is_string($handler[0])) {
            $handler[0] = new $handler[0];
        }

        return $handler;
    }

    private function dispatchRoute($httpMethod, $uri)
    {
        if (isset($this->staticRouteMap[$uri])) {
            return $this->dispatchStaticRoute($httpMethod, $uri);
        }

        return $this->dispatchVariableRoute($httpMethod, $uri);
    }

    private function dispatchStaticRoute($httpMethod, $uri)
    {
        $routes = $this->staticRouteMap[$uri];

        if (!isset($routes[$httpMethod])) {
            $httpMethod = $this->checkFallbacks($routes, $httpMethod);
        }

        return $routes[$httpMethod];
    }

    private function checkFallbacks($routes, $httpMethod)
    {
        $additional = [Route::ANY];

        if ($httpMethod === Route::HEAD) {
            $additional[] = Route::GET;
        }

        foreach ($additional as $method) {
            if (isset($routes[$method])) {
                return $method;
            }
        }

        $this->matchedRoute = $routes;

        throw new HttpMethodNotAllowedException('Allow: ' . implode(', ', array_keys($routes)));
    }

    private function dispatchVariableRoute($httpMethod, $uri)
    {
        foreach ($this->variableRouteData as $data) {
            if (!preg_match($data['regex'], $uri, $matches)) {
                continue;
            }

            $count = count($matches);

            while (!isset($data['routeMap'][$count++])) ;

            $routes = $data['routeMap'][$count - 1];

            if (!isset($routes[$httpMethod])) {
                $httpMethod = $this->checkFallbacks($routes, $httpMethod);
            }

            foreach (array_values($routes[$httpMethod][1]) as $i => $varName) {
                if (!isset($matches[$i + 1]) || $matches[$i + 1] === '') {
                    unset($routes[$httpMethod][1][$varName]);
                } else {
                    $routes[$httpMethod][1][$varName] = $matches[$i + 1];
                }
            }

            return $routes[$httpMethod];
        }

        // throw new HttpRouteNotFoundException('Route ' . $uri . ' does not exist');
        return false;
    }

}

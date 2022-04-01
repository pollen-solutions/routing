<?php

declare(strict_types=1);

namespace Pollen\Routing;

use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use League\Route\Route;
use League\Route\RouteConditionHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

class UrlMatcher implements UrlMatcherInterface
{
    protected RouterInterface $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param ServerRequestInterface $serverRequest
     *
     * @return ServerRequestInterface
     */
    public function handle(ServerRequestInterface $serverRequest): ServerRequestInterface
    {
        $routeCollector = $this->router->getRouteCollector();

        if (!$routeCollector->isRoutesPrepared()) {
            $routeCollector->prepareRoutes($serverRequest);
            $routeCollector->setRoutesPrepared();
        }
        $data = $routeCollector->getRoutesData();

        $method = $serverRequest->getMethod();
        $path = $serverRequest->getUri()->getPath();

        $match = (new GroupCountBased($data))->dispatch($method, $path);

        if ($match[0] === Dispatcher::FOUND) {
            $route = ($match[1] instanceof RouteConditionHandlerInterface) ? $match[1] : new Route($method, $path, $match[1]);
            $serverRequest = $serverRequest->withAttribute('_route', $route);

            foreach((array)$match[2] as $varKey => $varVal) {
                $serverRequest = $serverRequest->withAttribute("_$varKey", $varVal);
            }
        }

        return $serverRequest;
    }
}

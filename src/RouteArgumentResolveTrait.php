<?php declare(strict_types=1);

namespace Pollen\Routing;

use League\Route\Route;
use Pollen\ArgumentResolver\ArgumentResolver;
use Pollen\ArgumentResolver\Resolvers\ContainerResolver;

trait RouteArgumentResolveTrait
{
    protected function resolveRouteArguments(Route $route, $controller): array
    {
        $container = $this->getContainer();

        $resolvers = [];

        if ($container) {
            $resolvers[] = new ContainerResolver($container);
        }

        $resolvers[] = new RouteArgumentResolver($route);

        return (new ArgumentResolver($resolvers))->resolve($controller);
    }
}
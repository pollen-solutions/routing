<?php

declare(strict_types=1);

namespace Pollen\Routing;

use League\Route\Middleware\MiddlewareAwareInterface;
use Pollen\Http\RedirectResponseInterface;
use Pollen\Support\Proxy\ContainerProxyInterface;
use Pollen\Support\Proxy\HttpRequestProxyInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrRequest;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @mixin RouteCollectorAwareTrait
 */
interface RouterInterface extends
    ContainerProxyInterface,
    HttpRequestProxyInterface,
    RequestHandlerInterface,
    MiddlewareAwareInterface
{
    /**
     * Add a new route by its instance.
     *
     * @param RouteInterface $route
     *
     * @return static
     */
    public function addRoute(RouteInterface $route): RouterInterface;

    /**
     * Handle HTTP Response just before it is sent.
     *
     * @param PsrResponse $response
     *
     * @return PsrResponse
     */
    public function beforeSendResponse(PsrResponse $response): PsrResponse;

    /**
     * Get current route.
     *
     * @return RouteInterface|null
     */
    public function current(): ?RouteInterface;

    /**
     * Get current route name if its exists.
     *
     * @return string
     */
    public function currentRouteName(): ?string;

    /**
     * Get base path prefix for routes.
     *
     * @return string
     */
    public function getBasePrefix(): string;

    /**
     * Get base path suffix for routes.
     *
     * @return string
     */
    public function getBaseSuffix(): string;

    /**
     * Get fallback route handler.
     *
     * @return callable|null
     */
    public function getFallbackCallable(): ?callable;

    /**
     * Get named route instance.
     *
     * @param string $name
     *
     * @return RouteInterface|null
     */
    public function getNamedRoute(string $name): ?RouteInterface;

    /**
     * Get named route RedirectResponse object.
     *
     * @param string $name
     * @param array $args
     * @param bool $isAbsolute
     * @param int $status
     * @param array $headers
     *
     * @return RedirectResponseInterface
     */
    public function getNamedRouteRedirect(
        string $name,
        array $args = [],
        bool $isAbsolute = false,
        int $status = 302,
        array $headers = []
    ): RedirectResponseInterface;

    /**
     * Get named route url if its exists.
     *
     * @param string $name
     * @param array $args
     * @param bool $isAbsolute
     *
     * @return string
     */
    public function getNamedRouteUrl(string $name, array $args = [], bool $isAbsolute = false): ?string;

    /**
     * Get route collector instance.
     *
     * @return RouteCollectorInterface
     */
    public function getRouteCollector(): RouteCollectorInterface;

    /**
     * Récupération de la réponse HTTP de redirection vers une route.
     *
     * @param RouteInterface $route
     * @param array $args
     * @param bool $isAbsolute
     * @param int $status
     * @param array $headers
     *
     * @return RedirectResponseInterface
     */
    public function getRouteRedirect(
        RouteInterface $route,
        array $args = [],
        bool $isAbsolute = false,
        int $status = 302,
        array $headers = []
    ): RedirectResponseInterface;

    /**
     * Get RedirectResponse object from a route instance.
     *
     * @param RouteInterface $route
     * @param array $args
     * @param bool $isAbsolute
     *
     * @return string
     */
    public function getRouteUrl(RouteInterface $route, array $args = [], bool $isAbsolute = false): ?string;

    /**
     * Add a new route group.
     *
     * @param string $prefix
     * @param callable $group
     *
     * @return RouteGroupInterface
     */
    public function group(string $prefix, callable $group): RouteGroupInterface;

    /**
     * Checks if a fallback route handler exists.
     *
     * @return bool
     */
    public function hasFallback(): bool;

    /**
     * Add a new route.
     *
     * @param string $method
     * @param string $path
     * @param string|callable $handler
     *
     * @return RouteInterface
     */
    public function map(string $method, string $path, $handler): RouteInterface;

    /**
     * Set base path prefix for routes.
     *
     * @param string $basePrefix
     *
     * @return static
     */
    public function setBasePrefix(string $basePrefix): RouterInterface;

    /**
     * Set base path suffix for routes.
     *
     * @param string|null $baseSuffix
     *
     * @return static
     */
    public function setBaseSuffix(?string $baseSuffix = null): RouterInterface;

    /**
     * Set current route instance.
     *
     * @param RouteInterface $route
     *
     * @return static
     */
    public function setCurrentRoute(RouteInterface $route): RouterInterface;

    /**
     * Set the fallback route handler.
     *
     * @param callable|string $fallback
     *
     * @return $this
     */
    public function setFallback($fallback): RouterInterface;

    /**
     * HTTP request instance to handle route collection.
     *
     * @param PsrRequest $request
     *
     * @return PsrResponse
     */
    public function handle(PsrRequest $request): PsrResponse;

    /**
     * Send the HTTP response.
     *
     * @param PsrResponse $response
     *
     * @return bool
     */
    public function send(PsrResponse $response): bool;

    /**
     * Terminate the HTTP cycle.
     *
     * @param PsrRequest $request
     * @param PsrResponse $response
     *
     * @return void
     */
    public function terminate(PsrRequest $request, PsrResponse $response): void;
}
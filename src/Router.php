<?php

declare(strict_types=1);

namespace Pollen\Routing;

use Exception;
use FastRoute\BadRouteException;
use InvalidArgumentException;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Middleware\MiddlewareAwareTrait;
use League\Route\Http\Exception\HttpExceptionInterface as BaseHttpExceptionInterface;
use Pollen\Http\RedirectResponse;
use Pollen\Http\RedirectResponseInterface;
use Pollen\Http\Request;
use Pollen\Http\Response;
use Pollen\Http\ResponseInterface;
use Pollen\Support\Exception\ManagerRuntimeException;
use Pollen\Support\Proxy\ContainerProxy;
use Pollen\Support\Proxy\HttpRequestProxy;
use Pollen\Routing\Exception\HttpExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface as Container;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrRequest;
use Psr\Http\Server\MiddlewareInterface as BaseMiddlewareInterface;
use RuntimeException;

class Router implements RouterInterface
{
    use ContainerProxy;
    use HttpRequestProxy;
    use MiddlewareAwareTrait;
    use RouteCollectorAwareTrait;

    /**
     * Router main instance.
     * @var static|null
     */
    private static ?RouterInterface $instance = null;

    /**
     * Normalized base path prefix for routes.
     * @var string|null
     */
    private ?string $basePrefixNormalized = null;

    /**
     * Base path prefix for routes.
     * @var string|null
     */
    public ?string $basePrefix = null;

    /**
     * Base path suffix for routes.
     * @var string|null
     */
    public ?string $baseSuffix = null;

    /**
     * Current route instance.
     * @var RouteInterface|null
     */
    protected ?RouteInterface $currentRoute = null;

    /**
     * Fallback route handler.
     * @var callable|null
     */
    protected $fallback;

    /**
     * Route collector instance.
     * @var RouteCollectorInterface|null
     */
    protected ?RouteCollectorInterface $routeCollector = null;

    /**
     * @param Container|null $container
     */
    public function __construct(?Container $container = null)
    {
        if ($container !== null) {
            $this->setContainer($container);
        }

        $this->routeCollector = new RouteCollector($this);

        $this->setBasePrefix(Request::getFromGlobals()->getRewriteBase());

        if (!self::$instance instanceof static) {
            self::$instance = $this;
        }
    }

    /**
     * Get route main instance.
     *
     * @return static
     */
    public static function getInstance(): RouterInterface
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }
        throw new ManagerRuntimeException(sprintf('Unavailable [%s] instance', __CLASS__));
    }

    /**
     * @inheritDoc
     */
    public function addRoute(RouteInterface $route): RouterInterface
    {
        $this->getRouteCollector()->addRoute($route);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function beforeSendResponse(PsrResponse $response): PsrResponse
    {
        try {
            /** @var MiddlewareInterface|null $middleware */
            $middleware = $this->getRouteCollector()->shiftMiddleware();
        } catch (Exception $e) {
            $middleware = null;
        }

        if ($middleware === null) {
            return $response;
        }

        return $middleware->beforeSend($response, $this) ?: $response;
    }

    /**
     * @inheritDoc
     */
    public function current(): ?RouteInterface
    {
        return $this->currentRoute;
    }

    /**
     * @inheritDoc
     */
    public function currentRouteName(): ?string
    {
        return $this->currentRoute ? $this->currentRoute->getName() : null;
    }

    /**
     * @inheritDoc
     */
    public function getBasePrefix(): string
    {
        if ($this->basePrefixNormalized === null) {
            $this->basePrefixNormalized = $this->basePrefix ? '/' . rtrim(ltrim($this->basePrefix, '/'), '/') : '';
        }
        return $this->basePrefixNormalized;
    }

    /**
     * @inheritDoc
     */
    public function getBaseSuffix(): string
    {
        return $this->baseSuffix ?: '';
    }

    /**
     * @inheritDoc
     */
    public function getFallbackCallable(): ?callable
    {
        if (!$callable = $this->fallback) {
            return null;
        }

        if (is_string($callable) && strpos($callable, '::') !== false) {
            $callable = explode('::', $callable);
        }

        if (is_array($callable) && isset($callable[0]) && is_object($callable[0])) {
            $callable = [$callable[0], $callable[1]];
        }

        if (is_array($callable) && isset($callable[0]) && is_string($callable[0])) {
            $callable = [$this->resolveFallbackClass($callable[0]), $callable[1]];
        }

        if (is_string($callable)) {
            $callable = $this->resolveFallbackClass($callable);
        }

        if (!is_callable($callable)) {
            throw new RuntimeException('Could not resolve a callable Route Fallback');
        }
        return $callable;
    }

    /**
     * @inheritDoc
     */
    public function hasFallback(): bool
    {
        return $this->fallback !== null;
    }

    /**
     * @inheritDoc
     */
    public function getNamedRoute(string $name): ?RouteInterface
    {
        return $this->getRouteCollector()->getRoute($name);
    }

    /**
     * @inheritDoc
     */
    public function getNamedRouteRedirect(
        string $name,
        array $args = [],
        bool $isAbsolute = false,
        int $status = 302,
        array $headers = []
    ): RedirectResponseInterface {
        if ($route = $this->getNamedRoute($name)) {
            return $this->getRouteRedirect($route, $args, $isAbsolute, $status, $headers);
        }
        return new RedirectResponse('', $status, $headers);
    }

    /**
     * @inheritDoc
     */
    public function getNamedRouteUrl(string $name, array $args = [], bool $isAbsolute = false): ?string
    {
        if ($route = $this->getNamedRoute($name)) {
            return $this->getRouteUrl($route, $args, $isAbsolute);
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getRouteCollector(): RouteCollectorInterface
    {
        return $this->routeCollector;
    }

    /**
     * @inheritDoc
     */
    public function getRouteRedirect(
        RouteInterface $route,
        array $args = [],
        bool $isAbsolute = false,
        int $status = 302,
        array $headers = []
    ): RedirectResponseInterface {
        if ($url = $this->getRouteUrl($route, $args, $isAbsolute)) {
            return new RedirectResponse($url, $status, $headers);
        }
        return new RedirectResponse('', $status, $headers);
    }

    /**
     * @inheritDoc
     */
    public function getRouteUrl(RouteInterface $route, array $args = [], bool $isAbsolute = false): ?string
    {
        if (!$container = $this->getContainer()) {
            return null;
        }

        try {
            $request = $container->get(PsrRequest::class);
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            return null;
        }

        try {
            $generator = new UrlGenerator($route->getPath(), $request);

            return $generator
                ->setAbsoluteEnabled($isAbsolute)
                ->setHost($route->getHost())
                ->setPort($route->getPort())
                ->setScheme($route->getScheme())
                ->setUrlPatterns($this->getRouteCollector()->getUrlPatterns())
                ->get($args);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function group(string $prefix, callable $group): RouteGroupInterface
    {
        $_group = new RouteGroup($prefix, $group, $this);

        if ($container = $this->getContainer()) {
            $_group->setContainer($container);
        }

        $this->getRouteCollector()->addGroup($_group);

        return $_group;
    }

    public function head(string $path, $handler): RouteInterface
    {
        return $this->map('HEAD', $path, $handler);
    }

    /**
     * @inheritDoc
     */
    public function map(string $method, string $path, $handler): RouteInterface
    {
        $path = $this->getBasePrefix() . sprintf('/%s', ltrim($path, '/'));

        if (($suffix = $this->getBaseSuffix()) !== null) {
            if (substr($path, -1) === '/' && substr($suffix, 0, 1) === '/') {
                $path = rtrim($path, '/');
            }
            $path .= $suffix;
        }
        $route = new Route($method, $path, $handler);

        if ($container = $this->getContainer()) {
            $route->setContainer($container);
        }

        $this->addRoute($route);

        return $route;
    }

    /**
     * Get fallback route class instance.
     *
     * @param string $class
     *
     * @return object
     */
    protected function resolveFallbackClass(string $class): object
    {
        if (($container = $this->getContainer()) && $container->has($class)) {
            return $container->get($class);
        }

        if (class_exists($class)) {
            return new $class();
        }
        throw new RuntimeException('Route Fallback Class unresolvable');
    }

    protected function resolveMiddleware($middleware): BaseMiddlewareInterface
    {
        $container = $this->getContainer();

        if ($container === null && is_string($middleware) && class_exists($middleware)) {
            $middleware = new $middleware();
        }

        if ($container !== null && is_string($middleware) && $container->has($middleware)) {
            $middleware = $container->get($middleware);
        }

        if ($middleware instanceof BaseMiddlewareInterface) {
            return $middleware;
        }

        throw new InvalidArgumentException(sprintf('Could not resolve middleware class: %s', $middleware));
    }

    /**
     * @inheritDoc
     */
    public function setBasePrefix(string $basePrefix): RouterInterface
    {
        $this->basePrefixNormalized = null;
        $this->basePrefix = $basePrefix;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setBaseSuffix(?string $baseSuffix = null): RouterInterface
    {
        $this->baseSuffix = $baseSuffix;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setCurrentRoute(RouteInterface $route): RouterInterface
    {
        $this->currentRoute = $route;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setFallback($fallback): RouterInterface
    {
        $this->fallback = $fallback;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function handle(PsrRequest $request): PsrResponse
    {
        try {
            return $this->getRouteCollector()->dispatch($request);
        } catch (BadRouteException $e) {
            throw new RuntimeException(
                sprintf('Bad Route declaration thrown exception : [%s]', $e->getMessage())
            );
        } catch (HttpExceptionInterface|BaseHttpExceptionInterface $e) {
            if ($fallback = $this->getFallbackCallable()) {
                $response = $fallback($e);

                return $response instanceof ResponseInterface ? $response->psr() : $response;
            }
            return (new Response($e->getMessage(), $e->getStatusCode()))->psr();
        }
    }

    /**
     * @inheritDoc
     */
    public function send(PsrResponse $response): bool
    {
        $collect = $this->getRouteCollector();

        foreach ($this->getMiddlewareStack() as $middleware) {
            $collect->middleware($this->resolveMiddleware($middleware));
        }

        if ($route = $this->current()) {
            if ($group = $route->getParentGroup()) {
                foreach ($group->getMiddlewareStack() as $middleware) {
                    $collect->middleware($this->resolveMiddleware($middleware));
                }
            }

            foreach ($route->getMiddlewareStack() as $middleware) {
                $collect->middleware($this->resolveMiddleware($middleware));
            }
        }

        $response = $this->beforeSendResponse($response);

        return (new SapiEmitter())->emit($response);
    }

    /**
     * @inheritDoc
     */
    public function terminate(PsrRequest $request, PsrResponse $response): void
    {
        exit;
    }
}
<?php

declare(strict_types=1);

namespace Pollen\Routing;

use Pollen\Container\BootableServiceProvider;
use Pollen\Event\EventDispatcherInterface;
use Pollen\Kernel\Events\ConfigLoadEvent;
use Pollen\Routing\Middleware\XhrMiddleware;
use Pollen\Routing\Strategy\ApplicationStrategy;
use Pollen\Routing\Strategy\JsonStrategy;
use Laminas\Diactoros\ResponseFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RoutingServiceProvider extends BootableServiceProvider
{
    protected $provides = [
        RouterInterface::class,
        'routing.middleware.xhr',
        'routing.strategy.app',
        'routing.strategy.json'
    ];

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        try {
            /** @var EventDispatcherInterface $event */
            if ($event = $this->getContainer()->get(EventDispatcherInterface::class)) {
                $event->subscribeTo('config.load', static function (ConfigLoadEvent $event) {
                    if (is_callable($config = $event->getConfig('routing'))) {
                        $config($event->getApp()->get(RouterInterface::class), $event->getApp());
                    }
                });
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            unset($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->getContainer()->share(RouterInterface::class, function () {
            return new Router($this->getContainer());
        });
        $this->registerMiddlewares();
        $this->registerStrategies();

    }

    /**
     * Déclaration des middlewares.
     *
     * @return void
     */
    public function registerMiddlewares(): void
    {
        $this->getContainer()->add('routing.middleware.xhr', function () {
            return new XhrMiddleware();
        });
    }

    /**
     * Déclaration des stratégies.
     *
     * @return void
     */
    public function registerStrategies(): void
    {
        $this->getContainer()->add('routing.strategy.app', function () {
            $applicationStrategy = new ApplicationStrategy();
            $applicationStrategy->setContainer($this->getContainer());

            return $applicationStrategy;
        });
        $this->getContainer()->add('routing.strategy.json', function () {
            $jsonStrategy = new JsonStrategy(new ResponseFactory());
            $jsonStrategy->setContainer($this->getContainer());

            return $jsonStrategy;
        });
    }
}
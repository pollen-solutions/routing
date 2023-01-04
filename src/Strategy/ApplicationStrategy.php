<?php declare(strict_types=1);

namespace Pollen\Routing\Strategy;

use League\Route\Strategy\ApplicationStrategy as BaseApplicationStrategy;
use League\Route\Route;
use Pollen\Http\Response;
use Pollen\Http\ResponseInterface;
use Pollen\Routing\RouteArgumentResolveTrait;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrRequest;

class ApplicationStrategy extends BaseApplicationStrategy
{
    use RouteArgumentResolveTrait;
    
    /**
     * @param Route $route
     * @param PsrRequest $request
     *
     * @return PsrResponse
     */
    public function invokeRouteCallable(Route $route, PsrRequest $request): PsrResponse
    {
        $response = ($route->getCallable($this->getContainer()))(...$this->resolveRouteArguments($route));

        if ($response instanceof ResponseInterface) {
            $response = $response->psr();
        } elseif (!$response instanceof PsrResponse) {
            $response = is_string($response) ? (new Response($response))->psr() : (new Response())->psr();
        }

        return $this->decorateResponse($response);
    }
}

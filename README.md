# Routing Component

[![Latest Stable Version](https://img.shields.io/packagist/v/pollen-solutions/routing.svg?style=for-the-badge)](https://packagist.org/packages/pollen-solutions/routing)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-green?style=for-the-badge)](LICENSE.md)
[![PHP Supported Versions](https://img.shields.io/badge/PHP->=7.4-8892BF?style=for-the-badge&logo=php)](https://www.php.net/supported-versions.php)

Pollen Solutions **Routing** Component provide a layer of HTTP request mapping and HTTP response resolution.

## Installation

```bash
composer require pollen-solutions/routing
```

## Basic Usage

```php
<?php 

declare(strict_types=1);

use Pollen\Http\Request;
use Pollen\Http\Response;
use Pollen\Http\ResponseInterface;
use Pollen\Routing\Router;

// Router instantiation
$router = new Router();

// Map a route
$router->map('GET', '/', static function (): ResponseInterface {
    return new Response('<h1>Hello, World!</h1>');
});

$router->map('GET', '/phpinfo', static function () {
    ob_start();
    phpinfo();
    return new Response(ob_get_clean());
});

// Setting Handle Request (optional)
$psrRequest = Request::createFromGlobals()->psr();

// Map a Fallback Route (optional)
$router->setFallback(function () {
    return new Response('<h1>404 - Page not found !</h1>', 404);
});

// Catch HTTP Response
$response = $router->handle($psrRequest);

// Send the response to the browser
$router->send($response);

// Trigger the terminate event
$router->terminate($psrRequest, $response);
```

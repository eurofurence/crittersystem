<?php

declare(strict_types=1);

namespace Engelsystem\Middleware;

use Engelsystem\Application;
use Engelsystem\Controllers\MaintenanceController;
use Engelsystem\Http\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Don't block health monitoring and install workflow
        $path = (new Uri((string) $request->getUri()))->getPath();
        if ($request instanceof Request) {
            $path = $request->getPathInfo();
        }
        $path = urldecode($path);

        // Always allow health check and install workflow
        if ($path === '/health' || str_starts_with($path, '/admin/install')) {
            return $handler->handle($request);
        }

        /** @var MaintenanceController $controller */
        $controller = $this->app->make(MaintenanceController::class);
        $response = $controller->index();

        // If not in maintenance mode (204), continue pipeline
        if ($response->getStatusCode() === 204) {
            return $handler->handle($request);
        }

        // Otherwise return maintenance page (503)
        return $response;
    }
}

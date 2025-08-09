<?php

declare(strict_types=1);

namespace Engelsystem\Middleware;

use Engelsystem\Application;
use Engelsystem\Controllers\MigrationController;
use Engelsystem\Http\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MigrationGate implements MiddlewareInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Don't block health monitoring
        $path = (new Uri((string) $request->getUri()))->getPath();
        if ($request instanceof Request) {
            $path = $request->getPathInfo();
        }
        $path = urldecode($path);

        if ($path === '/health') {
            return $handler->handle($request);
        }

        /** @var MigrationController $controller */
        $controller = $this->app->make(MigrationController::class);
        $response = $controller->index();

        // If migration OK (204), continue pipeline
        if ($response->getStatusCode() === 204) {
            return $handler->handle($request);
        }

        // Otherwise return migration status page
        return $response;
    }
}

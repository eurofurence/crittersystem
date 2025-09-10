<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Middleware;

use Engelsystem\Application;
use Engelsystem\Controllers\MaintenanceController;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Middleware\MaintenanceMiddleware;
use Engelsystem\Test\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MaintenanceMiddlewareTest extends TestCase
{
    /**
     * @covers \Engelsystem\Middleware\MaintenanceMiddleware::__construct
     * @covers \Engelsystem\Middleware\MaintenanceMiddleware::process
     */
    public function testProcessNotInMaintenanceMode(): void
    {
        /** @var Application|MockObject $app */
        $app = $this->createMock(Application::class);

        /** @var MaintenanceController|MockObject $controller */
        $controller = $this->createMock(MaintenanceController::class);

        /** @var Response|MockObject $controllerResponse */
        $controllerResponse = $this->createMock(Response::class);
        $this->setExpects($controllerResponse, 'getStatusCode', [], 204);

        $this->setExpects($controller, 'index', [], $controllerResponse);
        $this->setExpects($app, 'make', [MaintenanceController::class], $controller);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $this->setExpects($request, 'getPathInfo', [], '/some/path');

        /** @var RequestHandlerInterface|MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        /** @var ResponseInterface|MockObject $handlerResponse */
        $handlerResponse = $this->createMock(ResponseInterface::class);
        $this->setExpects($handler, 'handle', [$request], $handlerResponse);

        $middleware = new MaintenanceMiddleware($app);
        $result = $middleware->process($request, $handler);

        $this->assertEquals($handlerResponse, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\MaintenanceMiddleware::process
     */
    public function testProcessHealthCheckBypass(): void
    {
        /** @var Application|MockObject $app */
        $app = $this->createMock(Application::class);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $this->setExpects($request, 'getPathInfo', [], '/health');

        /** @var RequestHandlerInterface|MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        /** @var ResponseInterface|MockObject $handlerResponse */
        $handlerResponse = $this->createMock(ResponseInterface::class);
        $this->setExpects($handler, 'handle', [$request], $handlerResponse);

        $middleware = new MaintenanceMiddleware($app);
        $result = $middleware->process($request, $handler);

        $this->assertEquals($handlerResponse, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\MaintenanceMiddleware::process
     */
    public function testProcessInstallWorkflowBypass(): void
    {
        /** @var Application|MockObject $app */
        $app = $this->createMock(Application::class);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $this->setExpects($request, 'getPathInfo', [], '/admin/install/status');

        /** @var RequestHandlerInterface|MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);

        /** @var ResponseInterface|MockObject $handlerResponse */
        $handlerResponse = $this->createMock(ResponseInterface::class);
        $this->setExpects($handler, 'handle', [$request], $handlerResponse);

        $middleware = new MaintenanceMiddleware($app);
        $result = $middleware->process($request, $handler);

        $this->assertEquals($handlerResponse, $result);
    }
}

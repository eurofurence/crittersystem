<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Controllers;

use Engelsystem\Config\Config;
use Engelsystem\Controllers\MaintenanceController;
use Engelsystem\Http\Response;
use Engelsystem\Test\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class MaintenanceControllerTest extends TestCase
{
    /**
     * @covers \Engelsystem\Controllers\MaintenanceController::__construct
     * @covers \Engelsystem\Controllers\MaintenanceController::index
     */
    public function testIndexNotInMaintenanceMode(): void
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $config = new Config(['maintenance' => false]);

        $this->setExpects($response, 'withStatus', [204], $response);

        $controller = new MaintenanceController($response, $config);
        $result = $controller->index();

        $this->assertEquals($response, $result);
    }

    /**
     * @covers \Engelsystem\Controllers\MaintenanceController::index
     */
    public function testIndexInMaintenanceMode(): void
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $config = new Config([
            'maintenance' => true,
            'app_name' => 'Test App',
        ]);

        // The response object should be called with method chaining
        $response->expects($this->atLeastOnce())
            ->method('withStatus')
            ->willReturn($response);

        $response->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturn($response);

        $response->expects($this->atLeastOnce())
            ->method('withContent')
            ->willReturn($response);

        $controller = new MaintenanceController($response, $config);
        $result = $controller->index();

        $this->assertEquals($response, $result);
    }

    /**
     * @covers \Engelsystem\Controllers\MaintenanceController::index
     */
    public function testIndexInMaintenanceModeDefaultAppName(): void
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $config = new Config(['maintenance' => true]);

        // The response object should be called with method chaining
        $response->expects($this->atLeastOnce())
            ->method('withStatus')
            ->willReturn($response);

        $response->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturn($response);

        $response->expects($this->atLeastOnce())
            ->method('withContent')
            ->willReturn($response);

        $controller = new MaintenanceController($response, $config);
        $result = $controller->index();

        $this->assertEquals($response, $result);
    }
}

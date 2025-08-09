<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Controllers;

use Engelsystem\Controllers\HealthController;
use Engelsystem\Http\Response;
use Engelsystem\Test\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class HealthControllerTest extends TestCase
{
    /**
     * @covers \Engelsystem\Controllers\HealthController::__construct
     * @covers \Engelsystem\Controllers\HealthController::index
     */
    public function testIndex(): void
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $this->setExpects($response, 'withHeader', ['Content-Type', 'application/json'], $response, 1);
        $this->setExpects(
            $response,
            'withHeader',
            ['Cache-Control', 'no-store, no-cache, must-revalidate'],
            $response,
            1
        );
        $this->setExpects($response, 'withContent', [$this->callback(function ($json) {
            $data = json_decode($json, true);
            return is_array($data) && ($data['status'] ?? null) === 'ok' && isset($data['time']);
        })], $response);

        $controller = new HealthController($response);
        $controller->index();
    }
}

<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Middleware;

use Engelsystem\Middleware\CertificationPermissions;
use Engelsystem\Test\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class CertificationPermissionsTest extends TestCase
{
    protected CertificationPermissions $middleware;
    protected LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->middleware = new CertificationPermissions($this->logger);
    }

    /**
     * Test middleware can be instantiated
     */
    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CertificationPermissions::class, $this->middleware);
    }

    /**
     * Test middleware is a PSR-15 middleware
     */
    public function testIsMiddleware(): void
    {
        $this->assertInstanceOf(\Psr\Http\Server\MiddlewareInterface::class, $this->middleware);
    }

    /**
     * Test middleware has logger dependency
     */
    public function testHasLoggerDependency(): void
    {
        $reflection = new \ReflectionClass($this->middleware);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('log', $parameters[0]->getName());
    }
}

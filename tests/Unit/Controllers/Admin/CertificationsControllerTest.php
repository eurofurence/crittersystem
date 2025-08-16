<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Controllers\Admin;

use Engelsystem\Controllers\Admin\CertificationsController;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Models\Certification;
use Engelsystem\Services\CertificationService;
use Engelsystem\Test\Unit\Controllers\ControllerTest;
use PHPUnit\Framework\MockObject\MockObject;

class CertificationsControllerTest extends ControllerTest
{
    protected CertificationService|MockObject $certificationService;
    protected Redirector|MockObject $redirect;
    protected \Psr\Log\Test\TestLogger $log;
    protected CertificationsController $controller;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->certificationService = $this->createMock(CertificationService::class);
        $this->redirect = $this->createMock(Redirector::class);
        $this->log = $this->createMock(\Psr\Log\Test\TestLogger::class);

        $this->controller = new CertificationsController(
            $this->log,
            $this->certificationService,
            $this->redirect,
            $this->response
        );
    }

    /**
     * Test controller can be instantiated
     */
    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CertificationsController::class, $this->controller);
    }

    /**
     * Test index method calls service and returns view
     */
    public function testIndex(): void
    {
        $certifications = collect([
            Certification::factory()->make(['id' => 1, 'name' => 'First Aid']),
            Certification::factory()->make(['id' => 2, 'name' => 'Safety']),
        ]);

        $request = new Request();

        $this->certificationService
            ->expects($this->once())
            ->method('getAllCertifications')
            ->willReturn($certifications);

        $this->response->expects($this->once())
            ->method('withView')
            ->with('admin/certifications/index', $this->isType('array'))
            ->willReturn($this->response);

        $result = $this->controller->index($request);
        $this->assertEquals($this->response, $result);
    }

    /**
     * Test create method returns view
     */
    public function testCreate(): void
    {
        $this->response->expects($this->once())
            ->method('withView')
            ->with('admin/certifications/create')
            ->willReturn($this->response);

        $result = $this->controller->create();
        $this->assertEquals($this->response, $result);
    }

    /**
     * Test store method validation and creation
     */
    public function testStore(): void
    {
        $requestData = [
            'name' => 'Test Certification',
            'description' => 'Test Description',
            'validity_months' => '12',
            'self_confirmation_allowed' => '1',
            'confirmation_required' => '0',
            'is_active' => '1',
        ];

        $request = new Request([], $requestData);
        $certification = Certification::factory()->make($requestData);

        $this->certificationService
            ->expects($this->once())
            ->method('createCertification')
            ->willReturn($certification);

        $this->redirect
            ->expects($this->once())
            ->method('to')
            ->with('/admin/certifications')
            ->willReturn($this->response);

        $result = $this->controller->store($request);
        $this->assertEquals($this->response, $result);
    }

    /**
     * Test controller permissions are set correctly
     */
    public function testPermissions(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $permissionsProperty = $reflection->getProperty('permissions');
        $permissionsProperty->setAccessible(true);
        $permissions = $permissionsProperty->getValue($this->controller);

        $expectedPermissions = [
            'certificates.admin',
            'certificates.manage',
            'admin_certificates',
        ];

        $this->assertEquals($expectedPermissions, $permissions);
    }
}

<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Controllers;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Controllers\UserCertificationsController;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\Validation\Validator;
use Engelsystem\Models\Certification;
use Engelsystem\Models\CertificationUser;
use Engelsystem\Models\User\User;
use Engelsystem\Services\CertificationService;
use Engelsystem\Test\Unit\HasDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\Test\TestLogger;

class UserCertificationsControllerTest extends ControllerTest
{
    use ArraySubsetAsserts;
    use HasDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->initDatabase();
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::__construct
     * @covers \Engelsystem\Controllers\UserCertificationsController::apply
     */
    public function testApply(): void
    {
        $user = $this->createUser();
        $certification = $this->createCertification();
        $applicationRecord = $this->createCertificationUser($user, $certification, 'pending');

        $request = new Request([], [
            'certification_uuid' => $certification->uuid,
            'confirmed' => '1',
        ]);
        $request->attributes->set('user_id', $user->id);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();
        $validator = new Validator(); // phpcs:ignore

        // Mock the auth() helper to return our test user
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        // Mock service method
        $service->expects($this->once())
            ->method('applyCertification')
            ->with($user, $certification, $this->stringContains('Application submitted from IP'))
            ->willReturn($applicationRecord);

        // Mock redirect response
        $redirect->expects($this->once())
            ->method('to')
            ->with('/user/certifications')
            ->willReturn($response);

        /** @var UserCertificationsController|MockObject $controller */
        $controller = $this->getMockBuilder(UserCertificationsController::class)
            ->setConstructorArgs([$log, $service, $response, $redirect])
            ->onlyMethods(['addNotification', 'validate'])
            ->getMock();

        $controller->expects($this->once())
            ->method('validate')
            ->with($request, [
                'certification_uuid' => 'required',
                'confirmed' => 'required|accepted',
            ])
            ->willReturn([
                'certification_uuid' => $certification->uuid,
                'confirmed' => '1',
            ]);

        $controller->expects($this->once())
            ->method('addNotification')
            ->with('certification.apply.success');

        $result = $controller->apply($request);

        $this->assertEquals($response, $result);
        $this->assertTrue($log->hasInfo('User successfully applied for certification'));
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::apply
     */
    public function testApplyInactiveCertification(): void
    {
        $user = $this->createUser();
        $certification = $this->createCertification(['is_active' => false]);

        $request = new Request([], [
            'certification_uuid' => $certification->uuid,
            'confirmed' => '1',
        ]);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();

        // Mock the auth() helper
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        // Mock redirect response
        $redirect->expects($this->once())
            ->method('to')
            ->with('/user/certifications')
            ->willReturn($response);

        /** @var UserCertificationsController|MockObject $controller */
        $controller = $this->getMockBuilder(UserCertificationsController::class)
            ->setConstructorArgs([$log, $service, $response, $redirect])
            ->onlyMethods(['addNotification', 'validate'])
            ->getMock();

        $controller->expects($this->once())
            ->method('validate')
            ->willReturn([
                'certification_uuid' => $certification->uuid,
                'confirmed' => '1',
            ]);

        $controller->expects($this->once())
            ->method('addNotification')
            ->with('certification.apply.inactive', NotificationType::ERROR);

        $result = $controller->apply($request);

        $this->assertEquals($response, $result);
        $this->assertTrue($log->hasWarning('Application attempted on inactive certification'));
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::apply
     */
    public function testApplyExistingPendingApplication(): void
    {
        $user = $this->createUser();
        $certification = $this->createCertification();
        $existingApplication = $this->createCertificationUser($user, $certification, 'pending'); // phpcs:ignore

        $request = new Request([], [
            'certification_uuid' => $certification->uuid,
            'confirmed' => '1',
        ]);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();

        // Mock the auth() helper
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        // Mock redirect response
        $redirect->expects($this->once())
            ->method('to')
            ->with('/user/certifications')
            ->willReturn($response);

        /** @var UserCertificationsController|MockObject $controller */
        $controller = $this->getMockBuilder(UserCertificationsController::class)
            ->setConstructorArgs([$log, $service, $response, $redirect])
            ->onlyMethods(['addNotification', 'validate'])
            ->getMock();

        $controller->expects($this->once())
            ->method('validate')
            ->willReturn([
                'certification_uuid' => $certification->uuid,
                'confirmed' => '1',
            ]);

        $controller->expects($this->once())
            ->method('addNotification')
            ->with('certification.apply.already_pending', NotificationType::WARNING);

        $result = $controller->apply($request);

        $this->assertEquals($response, $result);
        $this->assertTrue($log->hasInfo('Application attempted with existing certification'));
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::apply
     */
    public function testApplyCertificationNotFound(): void
    {
        $user = $this->createUser();

        $request = new Request([], [
            'certification_uuid' => 'non-existent-uuid',
            'confirmed' => '1',
        ]);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();

        // Mock the auth() helper
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        // Mock redirect response
        $redirect->expects($this->once())
            ->method('to')
            ->with('/user/certifications')
            ->willReturn($response);

        /** @var UserCertificationsController|MockObject $controller */
        $controller = $this->getMockBuilder(UserCertificationsController::class)
            ->setConstructorArgs([$log, $service, $response, $redirect])
            ->onlyMethods(['addNotification', 'validate'])
            ->getMock();

        $controller->expects($this->once())
            ->method('validate')
            ->willReturn([
                'certification_uuid' => 'non-existent-uuid',
                'confirmed' => '1',
            ]);

        $controller->expects($this->once())
            ->method('addNotification')
            ->with('certification.apply.not_found', NotificationType::ERROR);

        $result = $controller->apply($request);

        $this->assertEquals($response, $result);
        $this->assertTrue($log->hasError('Application attempted for non-existent certification'));
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::withdrawApplication
     */
    public function testWithdrawApplication(): void
    {
        $user = $this->createUser();
        $certification = $this->createCertification();
        $pendingApplication = $this->createCertificationUser($user, $certification, 'pending');

        $request = new Request([], [
            'certification_uuid' => $certification->uuid,
            'confirmed' => '1',
        ]);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();

        // Mock the auth() helper
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        // Mock redirect response
        $redirect->expects($this->once())
            ->method('to')
            ->with('/user/certifications')
            ->willReturn($response);

        /** @var UserCertificationsController|MockObject $controller */
        $controller = $this->getMockBuilder(UserCertificationsController::class)
            ->setConstructorArgs([$log, $service, $response, $redirect])
            ->onlyMethods(['addNotification', 'validate'])
            ->getMock();

        $controller->expects($this->once())
            ->method('validate')
            ->willReturn([
                'certification_uuid' => $certification->uuid,
                'confirmed' => '1',
            ]);

        $controller->expects($this->once())
            ->method('addNotification')
            ->with('certification.withdraw.success');

        $result = $controller->withdrawApplication($request);

        $this->assertEquals($response, $result);
        $this->assertTrue($log->hasInfo('User withdrew pending certification application'));

        // Verify the application was deleted
        $this->assertNull(CertificationUser::find($pendingApplication->id));
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::withdrawApplication
     */
    public function testWithdrawApplicationNotFound(): void
    {
        $user = $this->createUser();
        $certification = $this->createCertification();

        $request = new Request([], [
            'certification_uuid' => $certification->uuid,
            'confirmed' => '1',
        ]);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();

        // Mock the auth() helper
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        // Mock redirect response
        $redirect->expects($this->once())
            ->method('to')
            ->with('/user/certifications')
            ->willReturn($response);

        /** @var UserCertificationsController|MockObject $controller */
        $controller = $this->getMockBuilder(UserCertificationsController::class)
            ->setConstructorArgs([$log, $service, $response, $redirect])
            ->onlyMethods(['addNotification', 'validate'])
            ->getMock();

        $controller->expects($this->once())
            ->method('validate')
            ->willReturn([
                'certification_uuid' => $certification->uuid,
                'confirmed' => '1',
            ]);

        $controller->expects($this->once())
            ->method('addNotification')
            ->with('certification.withdraw.not_found', NotificationType::ERROR);

        $result = $controller->withdrawApplication($request);

        $this->assertEquals($response, $result);
        $this->assertTrue($log->hasWarning('Withdrawal attempted for non-existent pending application'));
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::showApplyForm
     */
    public function testShowApplyForm(): void
    {
        $user = $this->createUser();
        $certification = $this->createCertification();

        $request = new Request();
        $request->attributes->set('certification_uuid', $certification->uuid);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();

        // Mock the auth() helper
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        $response->expects($this->once())
            ->method('withView')
            ->with('user/certifications/apply', [
                'user' => $user,
                'certification' => $certification,
                'existing_certification' => null,
            ])
            ->willReturn($response);

        $controller = new UserCertificationsController($log, $service, $response, $redirect);

        $result = $controller->showApplyForm($request);

        $this->assertEquals($response, $result);
    }

    /**
     * @covers \Engelsystem\Controllers\UserCertificationsController::showApplyForm
     */
    public function testShowApplyFormInactiveCertification(): void
    {
        $user = $this->createUser();
        $certification = $this->createCertification(['is_active' => false]);

        $request = new Request();
        $request->attributes->set('certification_uuid', $certification->uuid);

        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        /** @var Redirector|MockObject $redirect */
        $redirect = $this->createMock(Redirector::class);
        /** @var CertificationService|MockObject $service */
        $service = $this->createMock(CertificationService::class);
        $log = new TestLogger();

        // Mock the auth() helper
        $this->app->bind('authenticator', function () use ($user) {
            $auth = $this->createMock(\Engelsystem\Helpers\Authenticator::class);
            $auth->method('user')->willReturn($user);
            return $auth;
        });

        // Mock redirect response
        $redirect->expects($this->once())
            ->method('to')
            ->with('/user/certifications')
            ->willReturn($response);

        /** @var UserCertificationsController|MockObject $controller */
        $controller = $this->getMockBuilder(UserCertificationsController::class)
            ->setConstructorArgs([$log, $service, $response, $redirect])
            ->onlyMethods(['addNotification'])
            ->getMock();

        $controller->expects($this->once())
            ->method('addNotification')
            ->with('certification.apply.inactive', NotificationType::ERROR);

        $result = $controller->showApplyForm($request);

        $this->assertEquals($response, $result);
    }

    protected function createUser(array $attributes = []): User
    {
        return User::factory($attributes)->create();
    }

    protected function createCertification(array $attributes = []): Certification
    {
        return Certification::factory(array_merge([
            'title' => 'Test Certification',
            'description' => 'Test certification description',
            'is_active' => true,
            'is_perpetual' => false,
            'validity_period_days' => 365,
            'allow_self_confirmation' => false,
        ], $attributes))->create();
    }

    protected function createCertificationUser(User $user, Certification $certification, string $status = 'pending'): CertificationUser // phpcs:ignore
    {
        return CertificationUser::factory([
            'user_id' => $user->id,
            'certification_id' => $certification->id,
            'status' => $status,
            'notes' => 'Test application',
        ])->create();
    }
}

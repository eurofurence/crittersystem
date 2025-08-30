<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Middleware;

use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Request;
use Engelsystem\Middleware\BackstagePermissions;
use Engelsystem\Models\User\User;
use Engelsystem\Test\Unit\TestCase;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class BackstagePermissionsTest extends TestCase
{
    protected BackstagePermissions $middleware;
    protected LoggerInterface|MockObject $logger;
    protected RequestHandlerInterface|MockObject $handler;
    protected ResponseInterface|MockObject $response;
    protected Request|MockObject $request;
    protected User|MockObject $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->request = $this->createMock(Request::class);
        $this->user = $this->createMock(User::class);

        $this->middleware = new BackstagePermissions($this->logger);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(BackstagePermissions::class, $this->middleware);
    }

    public function testNonBackstageRoutePassesThrough(): void
    {
        $this->request
            ->method('getAttribute')
            ->with('route-info')
            ->willReturn(['handler' => 'SomeOtherController@index']);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }

    public function testBackstageRouteWithoutAuthenticationThrowsForbidden(): void
    {
        $this->request
            ->method('getAttribute')
            ->with('route-info')
            ->willReturn(['handler' => 'BackstageController@index']);

        $this->request
            ->method('getClientIp')
            ->willReturn('127.0.0.1');

        $this->request
            ->method('getHeaderLine')
            ->with('User-Agent')
            ->willReturn('Test Browser');

        // Mock the auth() function to return null user
        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn(null);
        $this->app->instance('auth', $auth);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Backstage access attempted without authentication',
                $this->callback(function ($context) {
                    return isset($context['route']) && isset($context['ip']) && isset($context['user_agent']);
                })
            );

        $this->expectException(HttpForbidden::class);
        $this->expectExceptionMessage('Authentication required');

        $this->middleware->process($this->request, $this->handler);
    }

    public function testBackstageRouteWithoutPermissionThrowsForbidden(): void
    {
        $this->request
            ->method('getAttribute')
            ->with('route-info')
            ->willReturn(['handler' => 'BackstageController@index']);

        // Mock authenticated user without backstage permissions
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(false);

        $this->user
            ->method('hasPermission')
            ->willReturnMap([
                ['backstage.view', false],
                ['backstage.admin', false],
            ]);

        $this->user->name = 'testuser';
        $this->user->id = 123;

        $privileges = $this->createMock(Collection::class);
        $privileges->method('pluck')->with('name')->willReturn(collect(['some_other_permission']));
        $this->user->method('privileges')->willReturn($privileges);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Backstage view access denied',
                $this->callback(function ($context) {
                    return $context['user'] === 'testuser' && $context['user_id'] === 123;
                })
            );

        $this->expectException(HttpForbidden::class);
        $this->expectExceptionMessage('Backstage access denied. Contact an administrator for access.');

        $this->middleware->process($this->request, $this->handler);
    }

    public function testBackstageRouteWithViewPermissionSucceeds(): void
    {
        $this->request
            ->method('getAttribute')
            ->with('route-info')
            ->willReturn(['handler' => 'BackstageController@index']);

        // Mock authenticated user with backstage.view permission
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(true);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }

    public function testBackstageRouteWithAdminPermissionSucceeds(): void
    {
        $this->request
            ->method('getAttribute')
            ->with('route-info')
            ->willReturn(['handler' => 'BackstageController@index']);

        // Mock authenticated user with backstage.admin permission
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(true);

        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(true);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }

    public function testGoodiesRouteWithViewPermissionSucceeds(): void
    {
        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['route-info', [], ['handler' => 'BackstageGoodiesController@index']],
            ]);

        // Mock authenticated user with backstage view and goodies view permissions
        $this->user
            ->method('hasAnyPermission')
            ->willReturnMap([
                [['user.type.admin', 'backstage.admin', 'backstage.view'], true],
                [['user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view'],
                true],
            ]);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }

    public function testGoodiesRouteWithoutGoodiesPermissionThrowsForbidden(): void
    {
        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['route-info', [], ['handler' => 'BackstageGoodiesController@index']],
            ]);

        // Mock authenticated user with backstage view but no goodies permissions
        $this->user
            ->method('hasAnyPermission')
            ->willReturnMap([
                [['user.type.admin', 'backstage.admin', 'backstage.view'], true],
                [['user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view'],
                false],
            ]);

        $this->user->name = 'testuser';
        $this->user->id = 123;

        $privileges = $this->createMock(Collection::class);
        $privileges->method('pluck')->with('name')->willReturn(collect(['backstage.view']));
        $this->user->method('privileges')->willReturn($privileges);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Backstage goodies access denied');

        $this->expectException(HttpForbidden::class);
        $this->expectExceptionMessage('Goodies view access required for this operation.');

        $this->middleware->process($this->request, $this->handler);
    }

    public function testAdminOperationWithoutAdminPermissionThrowsForbidden(): void
    {
        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['route-info', [], ['handler' => 'BackstageGoodiesController@create']],
            ]);

        // Mock authenticated user with backstage view and goodies agent but not admin
        $this->user
            ->method('hasAnyPermission')
            ->willReturnMap([
                [['user.type.admin', 'backstage.admin', 'backstage.view'], true],
                [['user.type.admin', 'backstage.admin', 'backstage.goodies.admin'], false],
            ]);

        $this->user->name = 'testuser';
        $this->user->id = 123;

        $privileges = $this->createMock(Collection::class);
        $privileges->method('pluck')->with('name')->willReturn(collect(['backstage.view', 'backstage.goodies.agent']));
        $this->user->method('privileges')->willReturn($privileges);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Backstage goodies access denied');

        $this->expectException(HttpForbidden::class);
        $this->expectExceptionMessage('Goodies administration access required for this operation.');

        $this->middleware->process($this->request, $this->handler);
    }

    // System Admin Tests
    public function testSystemAdminHasBackstageAccess(): void
    {
        $this->request
            ->method('getAttribute')
            ->with('route-info')
            ->willReturn(['handler' => 'BackstageController@index']);

        // Mock authenticated user with user.type.admin permission
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(true);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }

    public function testSystemAdminHasGoodiesViewAccess(): void
    {
        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['route-info', [], ['handler' => 'BackstageGoodiesController@index']],
            ]);

        // Mock authenticated user with user.type.admin permission
        $this->user
            ->method('hasAnyPermission')
            ->willReturnMap([
                [['user.type.admin', 'backstage.admin', 'backstage.view'], true],
                [['user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view'],
                true],
            ]);

        $this->user->name = 'admin-user';
        $this->user->id = 1;

        $this->user
            ->method('hasPermission')
            ->with('user.type.admin')
            ->willReturn(true);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Backstage goodies access granted', $this->callback(function ($context) {
                return $context['user'] === 'admin-user' &&
                       $context['user_id'] === 1 &&
                       $context['is_system_admin'] === true;
            }));

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }

    public function testSystemAdminHasGoodiesAdminAccess(): void
    {
        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['route-info', [], ['handler' => 'BackstageGoodiesController@create']],
            ]);

        // Mock authenticated user with user.type.admin permission
        $this->user
            ->method('hasAnyPermission')
            ->willReturnMap([
                [['user.type.admin', 'backstage.admin', 'backstage.view'], true],
                [['user.type.admin', 'backstage.admin', 'backstage.goodies.admin'], true],
            ]);

        $this->user->name = 'admin-user';
        $this->user->id = 1;

        $this->user
            ->method('hasPermission')
            ->with('user.type.admin')
            ->willReturn(true);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Backstage goodies access granted', $this->callback(function ($context) {
                return $context['user'] === 'admin-user' &&
                       $context['user_id'] === 1 &&
                       $context['is_system_admin'] === true;
            }));

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }

    public function testSystemAdminBypassesAllPermissionChecks(): void
    {
        $this->request
            ->method('getAttribute')
            ->willReturnMap([
                ['route-info', [], ['handler' => 'BackstageGoodiesController@destroy']],
            ]);

        // Mock authenticated user with user.type.admin permission only
        $this->user
            ->method('hasAnyPermission')
            ->willReturnMap([
                [['user.type.admin', 'backstage.admin', 'backstage.view'], true],
                [['user.type.admin', 'backstage.admin', 'backstage.goodies.admin'], true],
            ]);

        $this->user->name = 'system-admin';
        $this->user->id = 1;

        $this->user
            ->method('hasPermission')
            ->willReturnMap([
                ['user.type.admin', true],
                ['backstage.admin', false],
                ['backstage.view', false],
                ['backstage.goodies.admin', false],
                ['backstage.goodies.agent', false],
                ['backstage.goodies.view', false],
            ]);

        $auth = $this->createMock(Authenticator::class);
        $auth->method('user')->willReturn($this->user);
        $this->app->instance('auth', $auth);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Backstage goodies access granted', $this->callback(function ($context) {
                return $context['user'] === 'system-admin' &&
                       $context['user_id'] === 1 &&
                       $context['is_system_admin'] === true;
            }));

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result);
    }
}

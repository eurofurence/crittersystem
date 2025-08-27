<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Helpers;

use Engelsystem\Helpers\Authenticator;
use Engelsystem\Helpers\BackstagePermissionHelper;
use Engelsystem\Models\User\User;
use Engelsystem\Test\Unit\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\MockObject\MockObject;

class BackstagePermissionHelperTest extends TestCase
{
    protected User|MockObject $user;
    protected Authenticator|MockObject $auth;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createMock(User::class);
        $this->auth = $this->createMock(Authenticator::class);
        $this->app->instance('auth', $this->auth);
    }

    public function testHasViewAccessWithoutUser(): void
    {
        $this->auth->method('user')->willReturn(null);

        $this->assertFalse(BackstagePermissionHelper::hasViewAccess());
    }

    public function testHasViewAccessWithViewPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::hasViewAccess());
    }

    public function testHasViewAccessWithAdminPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::hasViewAccess());
    }

    public function testHasViewAccessWithoutPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(false);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertFalse(BackstagePermissionHelper::hasViewAccess());
    }

    public function testHasAdminAccessWithAdminPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::hasAdminAccess());
    }

    public function testHasAdminAccessWithoutPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(false);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertFalse(BackstagePermissionHelper::hasAdminAccess());
    }

    public function testCanViewGoodiesWithViewPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with([
                'user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view',
            ])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::canViewGoodies());
    }

    public function testCanDistributeGoodiesWithAgentPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with([
                'user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
            ])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::canDistributeGoodies());
    }

    public function testCanManageGoodiesWithAdminPermission(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with([
                'user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
            ])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::canManageGoodies());
    }

    public function testGetBackstagePermissionLevelAdmin(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertEquals('admin', BackstagePermissionHelper::getBackstagePermissionLevel());
    }

    public function testGetBackstagePermissionLevelView(): void
    {
        $this->user
            ->method('hasPermission')
            ->willReturnMap([
                ['user.type.admin', false],
                ['backstage.admin', false],
                ['backstage.view', true],
            ]);
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(false);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertEquals('view', BackstagePermissionHelper::getBackstagePermissionLevel());
    }

    public function testGetBackstagePermissionLevelNull(): void
    {
        $this->user
            ->method('hasPermission')
            ->willReturnMap([
                ['user.type.admin', false],
                ['backstage.admin', false],
                ['backstage.view', false],
            ]);
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(false);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertNull(BackstagePermissionHelper::getBackstagePermissionLevel());
    }

    public function testGetGoodiesPermissionLevelAdmin(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(true);
        $this->user
            ->method('hasPermission')
            ->willReturnMap([
                ['user.type.admin', false],
                ['backstage.admin', true],
                ['backstage.goodies.admin', false],
                ['backstage.goodies.agent', false],
                ['backstage.goodies.view', false],
            ]);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertEquals('admin', BackstagePermissionHelper::getGoodiesPermissionLevel());
    }

    public function testGetGoodiesPermissionLevelAgent(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(false);
        $this->user
            ->method('hasPermission')
            ->willReturnMap([
                ['user.type.admin', false],
                ['backstage.admin', false],
                ['backstage.goodies.admin', false],
                ['backstage.goodies.agent', true],
                ['backstage.goodies.view', false],
            ]);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertEquals('agent', BackstagePermissionHelper::getGoodiesPermissionLevel());
    }

    public function testGetGoodiesPermissionLevelView(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(false);
        $this->user
            ->method('hasPermission')
            ->willReturnMap([
                ['user.type.admin', false],
                ['backstage.admin', false],
                ['backstage.goodies.admin', false],
                ['backstage.goodies.agent', false],
                ['backstage.goodies.view', true],
            ]);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertEquals('view', BackstagePermissionHelper::getGoodiesPermissionLevel());
    }

    public function testGetUserBackstagePermissions(): void
    {
        $builder = $this->createMock(Builder::class);
        $collection = $this->createMock(Collection::class);

        $permissions = ['backstage.admin', 'backstage.goodies.admin'];

        $collection->method('toArray')->willReturn($permissions);
        $builder->method('pluck')->with('name')->willReturn($collection);
        $builder->method('whereIn')->with('name', [
            'user.type.admin',
            'backstage.admin',
            'backstage.view',
            'backstage.goodies.admin',
            'backstage.goodies.agent',
            'backstage.goodies.view',
        ])->willReturn($builder);

        $this->user->method('privileges')->willReturn($builder);
        $this->auth->method('user')->willReturn($this->user);

        $result = BackstagePermissionHelper::getUserBackstagePermissions();
        $this->assertEquals($permissions, $result);
    }

    public function testHasAnyBackstageAccessWithPermissions(): void
    {
        $builder = $this->createMock(Builder::class);
        $collection = $this->createMock(Collection::class);

        $permissions = ['backstage.view'];

        $collection->method('toArray')->willReturn($permissions);
        $builder->method('pluck')->with('name')->willReturn($collection);
        $builder->method('whereIn')->willReturn($builder);

        $this->user->method('privileges')->willReturn($builder);
        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::hasAnyBackstageAccess());
    }

    public function testHasAnyBackstageAccessWithoutPermissions(): void
    {
        $builder = $this->createMock(Builder::class);
        $collection = $this->createMock(Collection::class);

        $permissions = [];

        $collection->method('toArray')->willReturn($permissions);
        $builder->method('pluck')->with('name')->willReturn($collection);
        $builder->method('whereIn')->willReturn($builder);

        $this->user->method('privileges')->willReturn($builder);
        $this->auth->method('user')->willReturn($this->user);

        $this->assertFalse(BackstagePermissionHelper::hasAnyBackstageAccess());
    }

    public function testWithExplicitUser(): void
    {
        $explicitUser = $this->createMock(User::class);
        $explicitUser
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(true);

        // Should not call auth()->user() when explicit user provided
        $this->auth->expects($this->never())->method('user');

        $this->assertTrue(BackstagePermissionHelper::hasViewAccess($explicitUser));
    }

    // System Admin Tests
    public function testSystemAdminHasViewAccess(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin', 'backstage.view'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::hasViewAccess());
    }

    public function testSystemAdminHasAdminAccess(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::hasAdminAccess());
    }

    public function testSystemAdminCanViewGoodies(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with([
                'user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view',
            ])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::canViewGoodies());
    }

    public function testSystemAdminCanDistributeGoodies(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with([
                'user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
            ])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::canDistributeGoodies());
    }

    public function testSystemAdminCanManageGoodies(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with([
                'user.type.admin',
                'backstage.admin',
                'backstage.goodies.admin',
            ])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::canManageGoodies());
    }

    public function testSystemAdminGetBackstagePermissionLevel(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertEquals('admin', BackstagePermissionHelper::getBackstagePermissionLevel());
    }

    public function testSystemAdminGetGoodiesPermissionLevel(): void
    {
        $this->user
            ->method('hasAnyPermission')
            ->with(['user.type.admin', 'backstage.admin'])
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertEquals('admin', BackstagePermissionHelper::getGoodiesPermissionLevel());
    }

    public function testSystemAdminGetUserBackstagePermissions(): void
    {
        $this->user
            ->method('hasPermission')
            ->with('user.type.admin')
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $expectedPermissions = [
            'backstage.admin',
            'backstage.view',
            'backstage.goodies.admin',
            'backstage.goodies.agent',
            'backstage.goodies.view',
        ];

        $result = BackstagePermissionHelper::getUserBackstagePermissions();
        $this->assertEquals($expectedPermissions, $result);
    }

    public function testSystemAdminHasAnyBackstageAccess(): void
    {
        $this->user
            ->method('hasPermission')
            ->with('user.type.admin')
            ->willReturn(true);

        $this->auth->method('user')->willReturn($this->user);

        $this->assertTrue(BackstagePermissionHelper::hasAnyBackstageAccess());
    }
}

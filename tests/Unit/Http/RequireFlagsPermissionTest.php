<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Unit\Http;

use Glueful\Auth\UserIdentity;
use Glueful\Extensions\Flags\Http\RequireFlagsPermission;
use Glueful\Extensions\Flags\Tests\Support\FakePermissionManager;
use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;
use Glueful\Permissions\PermissionManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireFlagsPermissionTest extends FlagsTestCase
{
    public function testPermissionMiddlewareReturns403WithoutAuthenticatedUser(): void
    {
        $response = (new RequireFlagsPermission($this->appContext()))->handle(Request::create('/'), static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareReturns403WhenManagerUnavailable(): void
    {
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));
        $response = (new RequireFlagsPermission($this->appContext()))->handle($request, static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareReturns403WithRealManagerAndNoProvider(): void
    {
        $manager = new PermissionManager();
        $manager->clearProvider();
        $this->bind(PermissionManager::class, $manager);
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));
        $response = (new RequireFlagsPermission($this->appContext()))->handle($request, static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareReturns403WhenPermissionDenied(): void
    {
        $this->bind(PermissionManager::class, new FakePermissionManager(false));
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));
        $response = (new RequireFlagsPermission($this->appContext()))->handle($request, static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareCallsNextOnlyWhenAllowed(): void
    {
        $manager = new FakePermissionManager(true);
        $this->bind(PermissionManager::class, $manager);
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));
        $called = false;

        $response = (new RequireFlagsPermission($this->appContext()))->handle(
            $request,
            function () use (&$called): Response {
                $called = true;
                return new Response('ok', 200);
            },
            'flags.manage'
        );

        self::assertTrue($called);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['user-1', 'flags.manage', 'flags'], array_slice($manager->lastCall, 0, 3));
    }
}

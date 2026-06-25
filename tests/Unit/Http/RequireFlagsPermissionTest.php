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

    public function testPermissionMiddlewareReturns403WithoutPermissionParameter(): void
    {
        $this->bind(PermissionManager::class, new FakePermissionManager(true));
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));
        $called = false;

        $response = (new RequireFlagsPermission($this->appContext()))->handle(
            $request,
            function () use (&$called): Response {
                $called = true;
                return new Response('ok', 200);
            }
        );

        self::assertFalse($called);
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

    /**
     * The `auth.user` UserIdentity is set only by an OPTIONAL enricher most apps
     * don't register; the always-present `'user'` array must be honored, else the
     * API 403s every authenticated request.
     */
    public function testPermissionMiddlewareAllowsViaUserArrayFallback(): void
    {
        $manager = new FakePermissionManager(true);
        $this->bind(PermissionManager::class, $manager);
        $request = Request::create('/');
        // No auth.user — only the array attribute AuthMiddleware always sets.
        $request->attributes->set('user', ['uuid' => 'user-9', 'roles' => ['administrator']]);
        $called = false;

        $response = (new RequireFlagsPermission($this->appContext()))->handle(
            $request,
            function () use (&$called): Response {
                $called = true;
                return new Response('ok', 200);
            },
            'flags.view'
        );

        self::assertTrue($called);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['user-9', 'flags.view', 'flags'], array_slice($manager->lastCall, 0, 3));
    }

    public function testPermissionMiddlewareReturns403WhenUserArrayHasNoUuid(): void
    {
        $this->bind(PermissionManager::class, new FakePermissionManager(true));
        $request = Request::create('/');
        $request->attributes->set('user', ['roles' => ['administrator']]); // no uuid
        $response = (new RequireFlagsPermission($this->appContext()))->handle(
            $request,
            static fn(): string => 'next',
            'flags.view'
        );
        self::assertSame(403, $response->getStatusCode());
    }
}

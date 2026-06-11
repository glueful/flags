<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Unit\Support;

use Glueful\Extensions\Flags\Support\FlagContext;
use PHPUnit\Framework\TestCase;

final class FlagContextTest extends TestCase
{
    public function testSubjectKeysAndNormalizedArrays(): void
    {
        $context = new FlagContext(
            userUuid: 'user-1',
            tenantUuid: 'tenant-1',
            roles: ['admin', 'admin'],
            scopes: ['beta'],
            attributes: ['account' => 'acct-1']
        );

        self::assertSame(['admin'], $context->roles);
        self::assertSame('user-1', $context->subjectKey('user'));
        self::assertSame('tenant-1', $context->subjectKey('tenant'));
        self::assertSame('acct-1', $context->subjectKey('custom', 'account'));
        self::assertNull($context->subjectKey('custom', 'missing'));
    }
}

<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Unit\Events;

use Glueful\Events\Contracts\BaseEvent;
use Glueful\Extensions\Flags\Events\FlagCreated;
use Glueful\Extensions\Flags\Events\FlagRuleAdded;
use PHPUnit\Framework\TestCase;

final class FlagEventsTest extends TestCase
{
    public function testEventsExtendBaseEvent(): void
    {
        self::assertInstanceOf(BaseEvent::class, new FlagCreated('flag-1', 'new_editor'));
        self::assertInstanceOf(BaseEvent::class, new FlagRuleAdded('flag-1', 'new_editor', 'rule-1'));
    }
}

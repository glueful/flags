<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Events;

use Glueful\Events\Contracts\BaseEvent;

final class FlagUpdated extends BaseEvent
{
    public function __construct(public readonly string $flagUuid, public readonly string $key)
    {
        parent::__construct();
    }
}

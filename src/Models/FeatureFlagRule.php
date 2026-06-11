<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Models;

final readonly class FeatureFlagRule
{
    public function __construct(
        public string $uuid,
        public string $flagUuid,
        public int $priority,
        public string $type,
        public string $operator,
        public mixed $value = null,
        public ?int $percentage = null,
        public ?string $subject = null,
        public bool $enabled = true,
    ) {
    }
}

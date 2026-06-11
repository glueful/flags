<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Models;

final readonly class FeatureFlag
{
    /** @param list<FeatureFlagRule> $rules */
    public function __construct(
        public string $uuid,
        public string $key,
        public string $name,
        public ?string $description,
        public bool $enabled,
        public bool $defaultValue,
        public string $status,
        public ?string $createdBy = null,
        public array $rules = [],
    ) {
    }
}

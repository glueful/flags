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

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'default_value' => $this->defaultValue,
            'status' => $this->status,
            'created_by' => $this->createdBy,
            'rules' => array_map(
                static fn(FeatureFlagRule $rule): array => $rule->toArray(),
                $this->rules
            ),
        ];
    }
}

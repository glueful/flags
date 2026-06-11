<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Services;

use Glueful\Extensions\Flags\Models\FeatureFlag;

final class FeatureFlagCache
{
    /** @var array<string,FeatureFlag|null> */
    private array $items = [];

    public function get(string $key, ?string $environment): ?FeatureFlag
    {
        return $this->items[$this->key($key, $environment)] ?? null;
    }

    public function put(string $key, ?string $environment, ?FeatureFlag $flag): void
    {
        $this->items[$this->key($key, $environment)] = $flag;
    }

    public function clear(?string $key = null): void
    {
        if ($key === null) {
            $this->items = [];
            return;
        }

        foreach (array_keys($this->items) as $cacheKey) {
            if (str_starts_with($cacheKey, $key . ':')) {
                unset($this->items[$cacheKey]);
            }
        }
    }

    private function key(string $key, ?string $environment): string
    {
        return $key . ':' . ($environment ?? '');
    }
}

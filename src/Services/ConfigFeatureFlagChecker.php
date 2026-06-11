<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Services;

use Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface;
use Glueful\Extensions\Flags\Support\FlagContext;

final class ConfigFeatureFlagChecker implements FeatureFlagCheckerInterface
{
    /** @param array<string,bool> $flags */
    public function __construct(private array $flags = [])
    {
    }

    public function enabled(string $flag, FlagContext $context): bool
    {
        return $this->flags[$flag] ?? false;
    }
}

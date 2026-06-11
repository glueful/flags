<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Services;

use Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface;
use Glueful\Extensions\Flags\Support\FlagContext;

final class NullFeatureFlagChecker implements FeatureFlagCheckerInterface
{
    public function __construct(private bool $default = false)
    {
    }

    public function enabled(string $flag, FlagContext $context): bool
    {
        return $this->default;
    }
}

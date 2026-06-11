<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Contracts;

use Glueful\Extensions\Flags\Support\FlagContext;

interface FeatureFlagCheckerInterface
{
    public function enabled(string $flag, FlagContext $context): bool;
}

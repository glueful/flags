<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Services;

use Glueful\Extensions\Flags\Models\FeatureFlag;
use Glueful\Extensions\Flags\Support\FlagContext;
use Glueful\Extensions\Flags\Support\RuleMatcher;

final class FeatureFlagEvaluator
{
    public function __construct(private RuleMatcher $matcher = new RuleMatcher())
    {
    }

    public function evaluate(?FeatureFlag $flag, FlagContext $context, bool $missingDefault = false): bool
    {
        if ($flag === null || $flag->status !== 'active') {
            return $missingDefault;
        }

        if (!$flag->enabled) {
            return false;
        }

        foreach ($flag->rules as $rule) {
            if ($rule->enabled && $this->matcher->matches($flag, $rule, $context)) {
                return true;
            }
        }

        return $flag->defaultValue;
    }
}

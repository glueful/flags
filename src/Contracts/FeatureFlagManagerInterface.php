<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Contracts;

use Glueful\Extensions\Flags\Models\FeatureFlag;
use Glueful\Extensions\Flags\Models\FeatureFlagRule;

interface FeatureFlagManagerInterface extends FeatureFlagCheckerInterface
{
    public function get(string $flag): ?FeatureFlag;

    /** @param array<string,mixed> $data */
    public function create(array $data): FeatureFlag;

    /** @param array<string,mixed> $data */
    public function update(string $flag, array $data): FeatureFlag;

    /** @param array<string,mixed> $rule */
    public function addRule(string $flag, array $rule): FeatureFlagRule;

    public function removeRule(string $flag, string $ruleUuid): void;
}

<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Support;

use Glueful\Extensions\Flags\Models\FeatureFlag;
use Glueful\Extensions\Flags\Models\FeatureFlagRule;

final class RuleMatcher
{
    public function matches(FeatureFlag $flag, FeatureFlagRule $rule, FlagContext $context): bool
    {
        return match ($rule->type) {
            'user' => $this->compare($context->userUuid, $rule->operator, $rule->value),
            'tenant' => $this->compare($context->tenantUuid, $rule->operator, $rule->value),
            'role' => $this->containsAny($context->roles, $rule->value),
            'scope' => $this->containsAny($context->scopes, $rule->value),
            'attribute' => $this->attributeMatches($context, $rule),
            'environment' => $this->compare($context->environment, $rule->operator, $rule->value),
            'percentage' => $this->percentage($flag->key, $rule, $context),
            default => false,
        };
    }

    private function compare(?string $actual, string $operator, mixed $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        $values = is_array($expected) ? array_map('strval', $expected) : [(string) $expected];

        return match ($operator) {
            'not_in' => !in_array($actual, $values, true),
            default => in_array($actual, $values, true),
        };
    }

    /** @param list<string> $actual */
    private function containsAny(array $actual, mixed $expected): bool
    {
        $values = is_array($expected) ? array_map('strval', $expected) : [(string) $expected];

        return count(array_intersect($actual, $values)) > 0;
    }

    private function attributeMatches(FlagContext $context, FeatureFlagRule $rule): bool
    {
        if (!is_array($rule->value)) {
            return false;
        }

        $key = isset($rule->value['key']) && is_scalar($rule->value['key']) ? (string) $rule->value['key'] : '';
        $value = $rule->value['value'] ?? null;
        $actual = $context->attribute($key);

        return is_scalar($actual) && $this->compare((string) $actual, $rule->operator, $value);
    }

    private function percentage(string $flagKey, FeatureFlagRule $rule, FlagContext $context): bool
    {
        $subject = $rule->subject ?? 'user';
        $attributeKey = is_array($rule->value) && is_scalar($rule->value['attribute'] ?? null)
            ? (string) $rule->value['attribute']
            : null;
        $subjectKey = $context->subjectKey($subject, $attributeKey);
        if ($subjectKey === null || $rule->percentage === null) {
            return false;
        }

        $bucket = hexdec(substr(hash('sha256', $flagKey . $subjectKey), 0, 8)) % 100;

        return $bucket < max(0, min(100, $rule->percentage));
    }
}

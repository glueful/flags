<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Exceptions;

final class RuleNotFoundException extends \RuntimeException
{
    public static function forUuid(string $flagKey, string $ruleUuid): self
    {
        return new self(sprintf('Rule "%s" was not found on feature flag "%s".', $ruleUuid, $flagKey));
    }
}

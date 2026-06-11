<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Exceptions;

final class FlagNotFoundException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Feature flag "%s" was not found.', $key));
    }
}

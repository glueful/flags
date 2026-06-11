<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Support;

final class FlagContext
{
    public readonly ?string $userUuid;
    public readonly ?string $tenantUuid;
    public readonly ?string $environment;

    /** @var list<string> */
    public readonly array $roles;

    /** @var list<string> */
    public readonly array $scopes;

    /** @var array<string,mixed> */
    public readonly array $attributes;

    /**
     * @param list<string> $roles
     * @param list<string> $scopes
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        ?string $userUuid = null,
        ?string $tenantUuid = null,
        ?string $environment = null,
        array $roles = [],
        array $scopes = [],
        array $attributes = [],
    ) {
        $this->userUuid = $userUuid;
        $this->tenantUuid = $tenantUuid;
        $this->environment = $environment;
        $this->roles = $this->normalize($roles);
        $this->scopes = $this->normalize($scopes);
        $this->attributes = $attributes;
    }

    public function attribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function subjectKey(string $subject, ?string $attributeKey = null): ?string
    {
        return match ($subject) {
            'user' => $this->userUuid,
            'tenant' => $this->tenantUuid,
            'custom' => $attributeKey !== null && is_scalar($this->attribute($attributeKey))
                ? (string) $this->attribute($attributeKey)
                : null,
            default => null,
        };
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function normalize(array $values): array
    {
        return array_values(array_unique(array_map('strval', $values)));
    }
}

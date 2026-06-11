<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Support;

use Glueful\Auth\UserIdentity;
use Symfony\Component\HttpFoundation\Request;

final class FlagContextFactory
{
    /** @param array<string,mixed> $data */
    public function fromArray(array $data): FlagContext
    {
        return new FlagContext(
            userUuid: isset($data['user']) && is_scalar($data['user']) ? (string) $data['user'] : null,
            tenantUuid: isset($data['tenant']) && is_scalar($data['tenant']) ? (string) $data['tenant'] : null,
            environment: isset($data['environment']) && is_scalar($data['environment'])
                ? (string) $data['environment']
                : null,
            roles: array_values(array_filter(array_map('strval', (array) ($data['roles'] ?? [])))),
            scopes: array_values(array_filter(array_map('strval', (array) ($data['scopes'] ?? [])))),
            attributes: is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
        );
    }

    public function fromRequest(Request $request, ?string $environment = null): FlagContext
    {
        $user = $request->attributes->get('auth.user');
        $tenant = $request->attributes->get('tenant.id');

        return new FlagContext(
            userUuid: $user instanceof UserIdentity ? $user->id() : null,
            tenantUuid: is_scalar($tenant) ? (string) $tenant : null,
            environment: $environment,
            roles: $user instanceof UserIdentity ? $user->roles() : [],
            scopes: $user instanceof UserIdentity ? $user->scopes() : [],
            attributes: (array) $request->attributes->get('flags.attributes', []),
        );
    }
}

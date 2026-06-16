<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Http\Controllers;

use Glueful\Auth\UserIdentity;
use Glueful\Extensions\Flags\Exceptions\FlagNotFoundException;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Http\Response;
use Glueful\Routing\Attributes\ApiOperation;
use Glueful\Routing\Attributes\ApiResponse;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagController
{
    public function __construct(private FeatureFlagManager $manager, private FeatureFlagRepository $flags)
    {
    }

    /**
     * List feature flags.
     */
    #[ApiOperation(
        summary: 'List Feature Flags',
        description: 'Lists every feature flag (active and archived) with its enabled rules, ordered by key. '
            . 'Requires the `flags.view` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(200, description: 'Feature flags retrieved')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.view permission')]
    public function index(Request $request): Response
    {
        return Response::success(['flags' => $this->flags->all()], 'Feature flags retrieved.');
    }

    /**
     * Create a feature flag.
     */
    #[ApiOperation(
        summary: 'Create Feature Flag',
        description: 'Creates a feature flag definition. New flags start with no rules; the flag is off '
            . 'unless enabled and matched by a rule or its default_value is true. '
            . 'Body: `key` (required; unique flag key, [a-z0-9._-], 1-160 chars), `name` (display name, '
            . 'defaults to the key), `description`, `enabled` (master switch, defaults to false), '
            . '`default_value` (value returned when no rule matches, defaults to false), '
            . '`status` (active|archived, defaults to active). '
            . 'Requires the `flags.manage` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(201, description: 'Feature flag created')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.manage permission')]
    #[ApiResponse(
        422,
        description: 'Validation failed (missing/invalid key, invalid status, non-boolean toggle, or duplicate key)'
    )]
    public function store(Request $request): Response
    {
        try {
            return Response::created(
                ['flag' => $this->manager->create($this->body($request), $this->actorUuid($request))],
                'Feature flag created.'
            );
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['flag' => $e->getMessage()]);
        }
    }

    /**
     * Get a feature flag.
     */
    #[ApiOperation(
        summary: 'Get Feature Flag',
        description: 'Returns one feature flag with its enabled rules ordered by priority. '
            . 'Requires the `flags.view` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(200, description: 'Feature flag retrieved')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.view permission')]
    #[ApiResponse(404, description: 'Feature flag not found')]
    public function show(Request $request, string $key): Response
    {
        $flag = $this->manager->get($key);
        if ($flag === null) {
            return Response::notFound('Feature flag not found.');
        }

        return Response::success(['flag' => $flag], 'Feature flag retrieved.');
    }

    /**
     * Update a feature flag.
     */
    #[ApiOperation(
        summary: 'Update Feature Flag',
        description: 'Updates a feature flag and clears its per-request definition cache. '
            . 'Body: `name` (new display name), `description`, `enabled` (master switch; toggling dispatches '
            . 'FlagEnabled/FlagDisabled), `default_value` (value returned when no rule matches), '
            . '`status` (active|archived). '
            . 'Requires the `flags.manage` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(200, description: 'Feature flag updated')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.manage permission')]
    #[ApiResponse(404, description: 'Feature flag not found')]
    #[ApiResponse(
        422,
        description: 'Validation failed (invalid status, non-boolean toggle, or attempt to change the key)'
    )]
    public function update(Request $request, string $key): Response
    {
        try {
            return Response::success(
                ['flag' => $this->manager->update($key, $this->body($request), $this->actorUuid($request))],
                'Feature flag updated.'
            );
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['flag' => $e->getMessage()]);
        }
    }

    /**
     * Archive a feature flag.
     */
    #[ApiOperation(
        summary: 'Archive Feature Flag',
        description: 'Soft-deletes a flag by setting status=archived and enabled=false. Archived flags '
            . 'always evaluate to false (fail closed); the row is kept for audit history. '
            . 'Requires the `flags.manage` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(200, description: 'Feature flag archived')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.manage permission')]
    #[ApiResponse(404, description: 'Feature flag not found')]
    public function archive(Request $request, string $key): Response
    {
        try {
            return Response::success(
                ['flag' => $this->manager->update(
                    $key,
                    ['status' => 'archived', 'enabled' => false],
                    $this->actorUuid($request)
                )],
                'Feature flag archived.'
            );
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        }
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return array_merge($request->request->all(), is_array($data) ? $data : []);
    }

    private function actorUuid(Request $request): ?string
    {
        $user = $request->attributes->get('auth.user');

        return $user instanceof UserIdentity ? $user->id() : null;
    }
}

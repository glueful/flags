<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Http\Controllers;

use Glueful\Auth\UserIdentity;
use Glueful\Extensions\Flags\Exceptions\FlagNotFoundException;
use Glueful\Extensions\Flags\Exceptions\RuleNotFoundException;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Http\Response;
use Glueful\Routing\Attributes\ApiOperation;
use Glueful\Routing\Attributes\ApiResponse;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagRuleController
{
    public function __construct(private FeatureFlagManager $manager)
    {
    }

    /**
     * Add a targeting rule to a flag.
     */
    #[ApiOperation(
        summary: 'Add Flag Rule',
        description: 'Adds a targeting rule to a flag. Enabled rules run in priority order (ascending); '
            . 'the first matching rule turns the flag on for the evaluated context. '
            . 'Body: `type` (required; user|tenant|role|scope|attribute|environment|percentage), '
            . '`operator` (in|not_in, defaults to in), `value` (scalar or list for '
            . 'user/tenant/role/scope/environment; {key, value} for attribute; optional {attribute} for '
            . 'percentage with a custom subject), `priority` (evaluation order, ascending, defaults to 0), '
            . '`percentage` (rollout 0-100, percentage rules only), `subject` (percentage bucketing: '
            . 'user|tenant|custom, defaults to user), `enabled` (whether the rule participates, defaults to true). '
            . 'Requires the `flags.manage` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(201, description: 'Flag rule created')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.manage permission')]
    #[ApiResponse(404, description: 'Feature flag not found')]
    #[ApiResponse(
        422,
        description: 'Validation failed (missing/unknown rule type, bad operator, '
            . 'percentage outside 0-100, or bad subject)'
    )]
    public function store(Request $request, string $key): Response
    {
        try {
            return Response::created(
                ['rule' => $this->manager->addRule($key, $this->body($request), $this->actorUuid($request))],
                'Flag rule created.'
            );
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['rule' => $e->getMessage()]);
        }
    }

    /**
     * Remove a flag rule.
     */
    #[ApiOperation(
        summary: 'Remove Flag Rule',
        description: 'Soft-removes a rule by disabling it (enabled=false); the row is kept for audit '
            . 'history. Removal dispatches FlagRuleRemoved and records a rule_removed audit row '
            . 'with full before/after rule snapshots. An unknown or already-removed rule UUID '
            . 'returns 404. '
            . 'Requires the `flags.manage` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(200, description: 'Flag rule removed')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.manage permission')]
    #[ApiResponse(404, description: 'Feature flag or rule not found')]
    public function delete(Request $request, string $key, string $uuid): Response
    {
        try {
            $this->manager->removeRule($key, $uuid, $this->actorUuid($request));
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        } catch (RuleNotFoundException) {
            return Response::notFound('Flag rule not found.');
        }

        return Response::success([], 'Flag rule removed.');
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

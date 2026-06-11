<?php

declare(strict_types=1);

use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagController;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagEvaluateController;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagRuleController;
use Glueful\Routing\Router;

/** @var Router $router Router instance injected by RouteManifest::load() */

$router->group(['prefix' => '/flags', 'middleware' => ['auth']], function (Router $router): void {
    /**
     * @route GET /flags
     * @summary List Feature Flags
     * @description Lists every feature flag (active and archived) with its enabled rules, ordered by key.
     * @tag Flags
     * @response 200 application/json "Feature flags retrieved"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.view permission"
     */
    $router->get('', [FeatureFlagController::class, 'index'])->middleware('flags_permission:flags.view');

    /**
     * @route POST /flags
     * @summary Create Feature Flag
     * @description
     *   Creates a feature flag definition. New flags start with no rules; the flag is off
     *   unless enabled and matched by a rule or its default_value is true.
     * @tag Flags
     * @requestBody
     *   key:string="Unique flag key ([a-z0-9._-], 1-160 chars)" {required=key}
     *   name:string="Display name (defaults to the key)"
     *   description:string="Optional description"
     *   enabled:boolean="Master switch (defaults to false)"
     *   default_value:boolean="Value returned when no rule matches (defaults to false)"
     *   status:string="Flag status: active|archived (defaults to active)"
     *   created_by:string="Optional creator UUID"
     * @response 201 application/json "Feature flag created"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.manage permission"
     * @response 422 "Validation failed (missing/invalid key, invalid status, non-boolean toggle, or duplicate key)"
     */
    $router->post('', [FeatureFlagController::class, 'store'])->middleware('flags_permission:flags.manage');

    /**
     * @route GET /flags/{key}
     * @summary Get Feature Flag
     * @description Returns one feature flag with its enabled rules ordered by priority.
     * @tag Flags
     * @response 200 application/json "Feature flag retrieved"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.view permission"
     * @response 404 "Feature flag not found"
     */
    $router->get('/{key}', [FeatureFlagController::class, 'show'])->middleware('flags_permission:flags.view');

    /**
     * @route PATCH /flags/{key}
     * @summary Update Feature Flag
     * @description Updates a feature flag and clears its per-request definition cache.
     * @tag Flags
     * @requestBody
     *   name:string="New display name"
     *   description:string="New description"
     *   enabled:boolean="Master switch; toggling dispatches FlagEnabled/FlagDisabled"
     *   default_value:boolean="Value returned when no rule matches"
     *   status:string="Flag status: active|archived"
     * @response 200 application/json "Feature flag updated"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.manage permission"
     * @response 404 "Feature flag not found"
     * @response 422 "Validation failed (invalid status, non-boolean toggle, or attempt to change the key)"
     */
    $router->patch('/{key}', [FeatureFlagController::class, 'update'])->middleware('flags_permission:flags.manage');

    /**
     * @route DELETE /flags/{key}
     * @summary Archive Feature Flag
     * @description
     *   Soft-deletes a flag by setting status=archived and enabled=false. Archived flags
     *   always evaluate to false (fail closed); the row is kept for audit history.
     * @tag Flags
     * @response 200 application/json "Feature flag archived"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.manage permission"
     * @response 404 "Feature flag not found"
     */
    $router->delete('/{key}', [FeatureFlagController::class, 'archive'])->middleware('flags_permission:flags.manage');

    /**
     * @route POST /flags/{key}/rules
     * @summary Add Flag Rule
     * @description
     *   Adds a targeting rule to a flag. Enabled rules run in priority order (ascending);
     *   the first matching rule turns the flag on for the evaluated context.
     * @tag Flags
     * @requestBody
     *   type:string="Rule type: user|tenant|role|scope|attribute|environment|percentage" {required=type}
     *   operator:string="Comparison operator: in|not_in (defaults to in)"
     *   value:object="Rule value: scalar or list for user/tenant/role/scope/environment; {key, value} for attribute; optional {attribute} for percentage with a custom subject"
     *   priority:int="Evaluation order, ascending (defaults to 0)"
     *   percentage:int="Rollout percentage 0-100 (percentage rules only)"
     *   subject:string="Percentage bucketing subject: user|tenant|custom (defaults to user)"
     *   enabled:boolean="Whether the rule participates in evaluation (defaults to true)"
     * @response 201 application/json "Flag rule created"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.manage permission"
     * @response 404 "Feature flag not found"
     * @response 422 "Validation failed (missing/unknown rule type, bad operator, percentage outside 0-100, or bad subject)"
     */
    $router->post('/{key}/rules', [FeatureFlagRuleController::class, 'store'])->middleware('flags_permission:flags.manage');

    /**
     * @route DELETE /flags/{key}/rules/{uuid}
     * @summary Remove Flag Rule
     * @description
     *   Soft-removes a rule by disabling it (enabled=false); the row is kept for audit
     *   history. Removal dispatches FlagRuleRemoved and records a rule_removed audit row
     *   with full before/after rule snapshots. An unknown or already-removed rule UUID
     *   returns 404.
     * @tag Flags
     * @response 200 application/json "Flag rule removed"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.manage permission"
     * @response 404 "Feature flag or rule not found"
     */
    $router->delete('/{key}/rules/{uuid}', [FeatureFlagRuleController::class, 'delete'])
        ->middleware('flags_permission:flags.manage');

    /**
     * @route POST /flags/{key}/evaluate
     * @summary Evaluate Feature Flag
     * @description
     *   Evaluates a flag against a caller-supplied context and returns the boolean result.
     *   A missing flag returns the configured flags.default; environment defaults to the
     *   flags.environment config value when omitted.
     * @tag Flags
     * @requestBody
     *   user:string="User UUID to evaluate for"
     *   tenant:string="Tenant UUID to evaluate for"
     *   environment:string="Environment name (defaults to flags.environment config)"
     *   roles:array="Role names for role rules"
     *   scopes:array="Scope names for scope rules"
     *   attributes:object="Free-form attributes for attribute rules and custom percentage subjects"
     * @response 200 application/json "Feature flag evaluated"
     * @response 401 "Not authenticated"
     * @response 403 "Missing flags.evaluate permission"
     */
    $router->post('/{key}/evaluate', [FeatureFlagEvaluateController::class, 'evaluate'])
        ->middleware('flags_permission:flags.evaluate');
});

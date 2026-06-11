# Glueful Flags Extension Spec

**Status:** Draft v2  
**Package:** `glueful/flags`  
**Purpose:** Feature flags and rollout controls for Glueful apps.
**Framework floor:** `glueful/framework >=1.54.0`

## Summary

`glueful/flags` provides runtime feature checks, rollout rules, audience targeting, and optional management APIs for Glueful apps and extensions.

The extension is a first-party primitive, not a product surface. It should make it easy for apps and extensions that opt into `glueful/flags` to ask one question:

```php
$flags->enabled('new_editor', $context);
```

It should not become an experimentation analytics product, a billing entitlement system, or a full customer data platform.

## Boundary

### Core Should Own

Core should own nothing for v1.

Core does not currently expose a feature-flag seam. That is correct for now: the entitlement seam established the promotion rule that a contract moves into framework core only after a real cross-cutting consumer needs it without depending on the implementation extension. Lemma can depend on `glueful/flags` directly, so flags do not yet earn a core seam.

The contract ships inside the extension namespace:

```php
namespace Glueful\Extensions\Flags\Contracts;

interface FeatureFlagCheckerInterface
{
    public function enabled(string $flag, FlagContext $context): bool;
}
```

The extension should provide:

- `FlagContext` value object with common attributes:
  - `userUuid`
  - `tenantUuid`
  - `environment`
  - `roles`
  - `scopes`
  - `attributes`
- `NullFeatureFlagChecker`, defaulting to `false` unless explicitly configured otherwise.
- `ConfigFeatureFlagChecker` for simple app-level flags.
- Container binding for `FeatureFlagCheckerInterface`.

Core must not own:

- persisted flag definitions;
- targeting rules;
- percentage rollout;
- management UI;
- flag audit history;
- experimentation metrics.

Promotion to core is deferred until a framework subsystem or multiple first-party extensions need to check flags without requiring `glueful/flags`.

### Extension Should Own

`glueful/flags` owns:

- persisted feature flag definitions;
- rule evaluation;
- audience targeting;
- percentage rollouts;
- environment scoping;
- tenant/user targeting;
- optional admin API;
- audit/activity events for flag changes;
- CLI commands for flag management;
- docs and examples.

Framework facts this spec relies on:

- Glueful already supports extension service definitions through `ServiceProvider::services()`.
- Extension DSL service definitions can declare `tags`, and typed providers can expose static `tags()`.
- Glueful already has declarative extension permissions through `ServiceProvider::permissions()`.
- Glueful already has core events and activity logging that this extension can emit into.

Release sequencing:

- v1 can ship as a standalone extension on `glueful/framework >=1.54.0`.
- No framework release is required unless the checker contract is later promoted into core.
- If a future promotion happens, ship the framework contract/null binding first, then update this extension to override that binding.

## Relationship To Entitlements

Flags and entitlements have a similar shape but different meaning.

- **Flags** answer: "Is this behavior rolled out for this context?"
- **Entitlements** answer: "Is this account/tenant allowed to use this commercial capability?"

Do not merge them.

Flags fail closed by default: absent checker, missing flag, or disabled flag returns `false`. This is deliberately the opposite polarity from the entitlement seam's absent-allow default. Rollout gates should not accidentally expose unfinished behavior.

Examples:

- `new_editor` is a flag.
- `max_projects` is an entitlement limit.
- `advanced_workflows` can require both: entitled by plan, then gradually rolled out by flag.

## Data Model

Suggested tables:

```text
feature_flags
feature_flag_rules
feature_flag_audits
```

### `feature_flags`

Fields:

- `id` bigint primary auto-increment.
- `uuid` string(12), unique.
- `key` string(160), unique.
- `name` string(255).
- `description` text nullable.
- `enabled` boolean default false.
- `default_value` boolean default false.
- `status` string(20) default `active`.
- `created_by` string(12) nullable, indexed, no cross-package foreign key to users.
- `created_at` timestamp nullable.
- `updated_at` timestamp nullable.

Indexes:

- unique `uuid`.
- unique `key`.
- index `status`.
- index `created_by`.

Lifecycle:

- `enabled` controls evaluation.
- `status` controls management lifecycle, initially `active` or `archived`.
- Do not add `deleted_at` in v1. `enabled + status + soft delete` creates unclear state combinations.
- Allowed statuses must be constrained in code; avoid open-ended status strings.

### `feature_flag_rules`

Fields:

- `id` bigint primary auto-increment.
- `uuid` string(12), unique.
- `flag_uuid` string(12), indexed, references `feature_flags.uuid` in code, no DB foreign key.
- `priority` integer default 0.
- `type` string(40).
- `operator` string(40).
- `value` json nullable.
- `percentage` integer nullable.
- `subject` string(20) nullable.
- `enabled` boolean default true.
- `created_at` timestamp nullable.
- `updated_at` timestamp nullable.

Indexes:

- unique `uuid`.
- index `flag_uuid`.
- compound index `(flag_uuid, enabled, priority)`.

Rule types:

- `user`
- `tenant`
- `role`
- `scope`
- `attribute`
- `percentage`
- `environment`

### `feature_flag_audits`

Fields:

- `uuid`
- `flag_uuid`
- `action`
- `before`
- `after`
- `actor_uuid`
- `created_at`

Audit should be useful for operational history. Compliance-grade immutable audit remains outside this extension unless a future advanced audit extension is installed.

This audit table is deliberate even though some extensions use PSR-3/activity logs only. Flag changes are operationally important and can happen frequently during rollout; a table gives the extension a queryable flag-change history without making it a compliance-grade audit product.

Migration notes:

- Register migrations with source `glueful/flags`.
- Use the default migration tier unless another extension table is referenced.
- Do not create DB foreign keys to user or tenant tables.

## Evaluation Rules

Evaluation should be deterministic.

Order:

1. If the flag does not exist, return the configured default.
2. If the flag is disabled, return `false`.
3. Evaluate enabled rules by priority.
4. First decisive rule wins.
5. If no rule matches, return `default_value`.

Percentage rollout must be stable:

- hash `flag_key + subject_key`;
- the rollout subject is explicit per rule: `user`, `tenant`, or `custom`;
- default subject is `user`;
- `custom` requires an explicit context attribute key;
- never use random values at evaluation time.

Environment is a rule type, not a column on `feature_flags`. Flag keys are globally unique. Environment-specific behavior is modeled by rules.

## Flag Context

`FlagContextFactory` builds a `FlagContext` from the current runtime:

- HTTP routes: request user context, request attributes, headers, tenant context when available, and configured environment.
- Queue jobs: job payload/context attributes and configured environment.
- CLI: explicit command options and configured environment.

The factory must keep soft dependencies soft:

- read tenancy/user details only through framework contracts or class-exists/container checks;
- do not require `glueful/users` or `glueful/tenancy`;
- missing user/tenant data should produce a context with null IDs, not an exception.

## Public API

Primary service:

```php
interface FeatureFlagManagerInterface extends FeatureFlagCheckerInterface
{
    public function get(string $flag): ?FeatureFlag;

    public function create(array $data): FeatureFlag;

    public function update(string $flag, array $data): FeatureFlag;

    public function addRule(string $flag, array $rule): FeatureFlagRule;

    public function removeRule(string $flag, string $ruleUuid): void;
}
```

The checker interface is the stable extension seam. The manager is extension-specific.

## Routes

Optional admin routes:

```text
GET    /flags
POST   /flags
GET    /flags/{key}
PATCH  /flags/{key}
DELETE /flags/{key}
POST   /flags/{key}/rules
DELETE /flags/{key}/rules/{uuid}
POST   /flags/{key}/evaluate
```

Routes must require auth and permissions. Permission names should be declared by the extension.

Suggested permissions:

- `flags.view`
- `flags.manage`
- `flags.evaluate`

Declare these through `FlagsServiceProvider::permissions()` using `Glueful\Permissions\Catalog\Permission::define()`, not by seeding ad hoc permission rows.

Permission enforcement:

- Use an extension-owned route guard/middleware that calls `Glueful\Permissions\PermissionManager::can()` directly.
- Fail closed when the user is missing, the permission manager is unavailable, or permission is denied.
- Do not rely on declarative catalog registration alone; declaration is not enforcement.
- Do not rely on `gate_permissions`/`#[RequiresPermission]` unless the package raises its framework floor to a version where route handler metadata permission enforcement is verified.

## CLI

Suggested commands:

```text
php glueful flags:list
php glueful flags:get <key>
php glueful flags:enable <key>
php glueful flags:disable <key>
php glueful flags:evaluate <key> [--user=] [--tenant=] [--env=]
```

## Events

Emit events for:

- flag created;
- flag updated;
- flag enabled;
- flag disabled;
- rule added;
- rule removed.

These events allow logging, webhooks, and search/index integrations without coupling.

Events should extend `Glueful\Events\Contracts\BaseEvent` and be dispatched through `Glueful\Events\EventService`.

## Configuration

Suggested config key: `flags`.

```php
return [
    'enabled' => true,
    'default' => false,
    'cache_ttl' => 60,
    'environment' => env('APP_ENV', 'production'),
];
```

## Caching

Flag definitions should be cached by key and environment.

Invalidation:

- clear a single flag cache entry when changed;
- clear all flags when migrations or bulk updates run;
- do not cache evaluation results unless the context key is stable and explicit.

## Lemma Usage

Lemma can use flags for:

- enabling a new editor;
- staged rollout of the page builder;
- enabling preview features for selected tenants;
- gradual rollout of content workflows;
- hiding beta content-model field types.

Lemma should not use flags for plan enforcement. Use entitlements/subscriptions for commercial access.

## Testing Requirements

- Missing flag returns configured default.
- Disabled flag always returns false.
- Rule priority is deterministic.
- Percentage rollout is stable for the same subject.
- Tenant/user/role/attribute matching works.
- Cache invalidates after flag update.
- Extension `FeatureFlagCheckerInterface` resolves when the extension is enabled.
- Routes enforce permissions.
- CLI evaluation matches service evaluation.
- Extension route guard denies when permissions are absent.
- `FlagContextFactory` works in HTTP, queue, and CLI contexts.
- Environment-specific behavior is represented by rules.
- Globally duplicate flag keys are rejected.

## Decisions

1. **No core seam in v1.** Contracts and null/config checkers ship in the extension namespace. Core promotion waits for a real cross-cutting consumer.
2. **Fail closed.** Missing checker, missing flag, disabled flag, or unavailable permission enforcement returns denial/false.
3. **Flag keys are globally unique.** Environment-specific behavior is modeled with rules.
4. **Percentage rollout subject is explicit.** Rule subject is `user`, `tenant`, or `custom`; default is `user`.
5. **No soft delete in v1.** Lifecycle is `enabled` plus constrained `status`.
6. **Permission enforcement is extension-owned.** Routes call `PermissionManager::can()` through a guard, not just catalog declarations.

## Open Questions

None outstanding.

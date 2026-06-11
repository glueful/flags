# Glueful Flags

Feature flags and rollout controls for Glueful apps: flag definitions, targeting
rules, deterministic percentage rollouts, a management HTTP API, CLI commands,
audit rows, and lifecycle events -- all behind an extension-owned checker
contract.

Flags answers exactly one question: **"is this feature rolled out for this
runtime context?"** It is an operational rollout switchboard (dark launches,
staged rollouts, kill switches), not an access-control or billing layer.

## Install

```bash
composer require glueful/flags
php glueful extensions:enable flags
php glueful migrate:run
```

Requires `glueful/framework >=1.55.0`. The migration creates three tables:
`feature_flags`, `feature_flag_rules`, and `feature_flag_audits`.

## Checking a flag

The default binding for `FeatureFlagCheckerInterface` is the DB-backed checker:

```php
use Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface;
use Glueful\Extensions\Flags\Support\FlagContext;

$flags = app($context, FeatureFlagCheckerInterface::class);

if ($flags->enabled('new_editor', new FlagContext(userUuid: $userUuid))) {
    // rolled-out path
}
```

`FlagContext` carries everything a rule can target:

```php
$flagContext = new FlagContext(
    userUuid: 'user-1',
    tenantUuid: 'tenant-1',
    environment: 'production',
    roles: ['admin'],
    scopes: ['posts:write'],
    attributes: ['plan' => 'beta', 'device_id' => 'abc-123'],
);
```

`FlagContextFactory` builds contexts for you: `fromArray()` for payloads and
CLI input, `fromRequest()` for HTTP requests (reads the authenticated
`auth.user` identity, an optional `tenant.id` request attribute, and
`flags.attributes` request attributes).

The contracts live in the extension namespace
(`Glueful\Extensions\Flags\Contracts`); there is no framework-core seam. Code
that checks flags takes a dependency on this extension.

### Shipped checkers

| Checker | Behavior |
| --- | --- |
| `DatabaseFeatureFlagChecker` | Default binding. Loads definitions from the DB and evaluates rules. |
| `ConfigFeatureFlagChecker` | Constant `['flag' => bool]` map; handy for tests and bootstrapping. Unknown keys return `false`. |
| `NullFeatureFlagChecker` | Returns a constant (constructor default `false`) for every flag. |

The null checker defaults to **false** on purpose: an unrolled-out feature must
stay dark. This is the deliberate opposite of the framework's entitlement
default (`NullEntitlementChecker` is absent-allow), because the failure modes
differ -- see the boundary section below.

## Evaluation order

1. Missing flag returns the configured `flags.default` (default `false`).
2. A non-`active` flag (archived) returns `false` -- fails closed, even if
   `default_value` or `flags.default` is `true`.
3. A disabled flag (`enabled = false`) returns `false`.
4. Enabled rules run in `priority` order (ascending). The first matching rule
   returns `true`. Rules can only turn a flag on; a non-matching rule is
   skipped, never decisive.
5. If no rule matches, the flag's `default_value` is returned.

## Rules

Rule fields: `type`, `operator` (`in` | `not_in`, default `in`), `value`,
`priority` (ascending, default 0), `percentage`, `subject`, `enabled`.

| Type | Matches when | `value` shape |
| --- | --- | --- |
| `user` | context user UUID compares against value | scalar or list of UUIDs |
| `tenant` | context tenant UUID compares against value | scalar or list of UUIDs |
| `role` | any context role intersects value | scalar or list of role names |
| `scope` | any context scope intersects value | scalar or list of scope names |
| `attribute` | the named context attribute compares against value | `{"key": "plan", "value": "beta"}` |
| `environment` | context environment compares against value | scalar or list of environment names |
| `percentage` | subject hashes into the rollout bucket | optional `{"attribute": "device_id"}` for `subject=custom` |

`user`, `tenant`, `attribute`, and `environment` honor `not_in`; a missing
context value never matches either operator (fails closed). `role` and `scope`
match on any intersection.

There is no global environment gate: environment is just a rule type. Scope a
flag to staging with an `environment` rule, and pass the environment on the
`FlagContext` (the HTTP evaluate endpoint defaults it from `flags.environment`).

### Percentage rollouts

Percentage rules are deterministic and stable:

- Bucket = first 8 hex chars of `sha256(flag_key . subject_key)` as an integer,
  modulo 100. The rule matches when the bucket is below `percentage` (clamped
  to 0-100).
- No salt and no delimiter: the same flag key and subject always land in the
  same bucket, so raising a rollout from 10% to 20% keeps the original 10%
  enabled and config changes never silently rebucket users.
- `subject` picks the hashed identity per rule: `user` (default), `tenant`, or
  `custom` (reads the context attribute named by `value.attribute`).
- A missing subject key or missing `percentage` fails closed.

## Configuration (`config/flags.php`)

| Key | Default | Used for |
| --- | --- | --- |
| `default` | `false` | Result for a flag that does not exist in the DB. |
| `environment` | `env('APP_ENV', 'production')` | Default environment for the HTTP evaluate endpoint when the payload omits one. |
| `routes_enabled` | `true` | Set `false` to skip registering the `/flags` HTTP routes. |

Every key in the file is read by a code path; there are no reserved keys.

## Caching

Flag definitions are memoized **per request, in process**, keyed by flag key
and environment (negative lookups included). Manager writes (update, archive,
rule add/remove) clear the affected flag's entries. There is no shared cache
backend and no TTL-based caching yet; a config key for a TTL will be added
when a shared backend exists.

## Management API

All routes sit behind the `auth` middleware plus the extension's permission
guard (see Permissions). Set `flags.routes_enabled = false` to turn them off.

| Method | Path | Permission | Action |
| --- | --- | --- | --- |
| GET | `/flags` | `flags.view` | List flags with their enabled rules |
| POST | `/flags` | `flags.manage` | Create a flag |
| GET | `/flags/{key}` | `flags.view` | Show one flag (404 if missing) |
| PATCH | `/flags/{key}` | `flags.manage` | Update name/description/enabled/default_value/status |
| DELETE | `/flags/{key}` | `flags.manage` | Archive (soft delete: `status=archived`, `enabled=false`) |
| POST | `/flags/{key}/rules` | `flags.manage` | Add a rule |
| DELETE | `/flags/{key}/rules/{uuid}` | `flags.manage` | Soft-remove a rule (sets `enabled=false`; 404 for an unknown or already-removed rule) |
| POST | `/flags/{key}/evaluate` | `flags.evaluate` | Evaluate the flag for a supplied context |

Archive and rule removal are soft operations; rows are kept for audit history.

### Validation and error responses

Write payloads pass through `FlagPayloadValidator` before they touch the
database: `key` is required on create ([a-z0-9._-], 1-160 chars, unique) and
immutable on update; `status` must be `active|archived`; `enabled` and
`default_value` must be booleans; rule `type` must be one of the seven rule
types; `operator` must be `in|not_in`; `percentage` must be an integer 0-100
(required for percentage rules); `subject` must be `user|tenant|custom`
(`custom` requires `value.attribute`); `attribute` rules require `value.key`.
Updates only pass whitelisted fields through, so unknown payload fields are
dropped.

A validation failure returns the framework's **422** error envelope, with the
message under `error.details.flag` (or `error.details.rule` for rule
payloads):

```json
{
    "success": false,
    "message": "Validation failed",
    "error": {
        "code": 422,
        "details": { "flag": "status must be one of: active, archived." },
        "timestamp": "...",
        "request_id": "..."
    }
}
```

An unknown flag key on show/update/archive/rule writes, and an unknown (or
already-removed) rule UUID on rule removal, return the framework's **404**
envelope (same shape, `code: 404`, no `details`).

## CLI

| Command | Action |
| --- | --- |
| `flags:list` | Table of flags (key, enabled, default, status) |
| `flags:get <key>` | One flag as JSON |
| `flags:enable <key>` | Set `enabled = true` |
| `flags:disable <key>` | Set `enabled = false` |
| `flags:archive <key>` | Archive the flag |
| `flags:evaluate <key> [--user=] [--tenant=] [--env=]` | Print `true`/`false` for the given context |

## Permissions

The provider registers three permissions in the framework permission catalog,
all on the `flags` resource:

- `flags.view` -- read flag definitions
- `flags.manage` -- create/update/archive flags and rules
- `flags.evaluate` -- call the evaluate endpoint

Enforcement is the extension-owned `flags_permission` route middleware
(`RequireFlagsPermission`). It resolves the framework `PermissionManager` and
calls `PermissionManager::can()` with the authenticated user's roles, scopes,
route params, and JWT claims. It fails closed: no authenticated user, no
resolvable permission manager, or a denied check all return 403.

## Events

`FeatureFlagManager` dispatches PSR-14 events (when an `EventService` is
available) from `Glueful\Extensions\Flags\Events`:

| Event | Dispatched on |
| --- | --- |
| `FlagCreated` | flag created |
| `FlagUpdated` | any flag update (including archive) |
| `FlagEnabled` | update flipped `enabled` to `true` |
| `FlagDisabled` | update flipped `enabled` to `false` |
| `FlagRuleAdded` | rule added |
| `FlagRuleRemoved` | rule removed (including soft-removal via the API) |

Every manager write also records a row in `feature_flag_audits`
(`created`, `updated`, `rule_added`, `rule_removed`). The `before`/`after`
JSON columns carry **full snapshots**: flag writes store the complete flag
field set (including its enabled rules), and rule writes store the complete
rule field set, so the audit trail can reconstruct any change without
consulting the live tables.

## Flags vs entitlements

Do not conflate the two:

- **Flags** answer "is this feature rolled out for this context?" --
  operational, temporary, owned by engineering. Default-deny: an unknown or
  archived flag is dark.
- **Entitlements** (framework `Glueful\Entitlements` seam, implemented by
  `glueful/subscriptions`) answer "has this tenant paid for / been granted this
  capability?" -- commercial, durable, owned by the product catalog.

Gating a paid feature with a flag, or a rollout with an entitlement, puts the
wrong owner in charge of the switch. A feature can require both: entitled AND
rolled out.

## Testing

The suite runs without a framework app: unit tests cover the evaluator,
matcher, manager, and guard, and integration tests run the real migration and
provider wiring against in-suite SQLite.

```bash
composer install
vendor/bin/phpunit
```

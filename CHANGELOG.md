# Changelog

All notable changes to `glueful/flags` will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [1.0.0] - 2026-06-11

First release. **Feature flags and rollout controls** for Glueful: flag definitions with
targeting rules and deterministic percentage rollouts, evaluated through an extension-owned
checker contract. Requires `glueful/framework >=1.54.0`.

### Added

- **Schema (3 tables at DEFAULT priority):** `feature_flags` (unique `key`, master `enabled`
  switch, `default_value`, `status` active|archived), `feature_flag_rules` (per-flag rules
  with `priority`, `type`, `operator`, JSON `value`, `percentage`, `subject`, `enabled`, plus
  an `(flag_uuid, enabled, priority)` evaluation index), and `feature_flag_audits`
  (action log with before/after JSON columns).
- **Fail-closed evaluator** (`FeatureFlagEvaluator`): missing flag returns the configured
  `flags.default` (default `false`); archived/non-active flags always return `false` even
  when defaults allow; disabled flags return `false`; enabled rules run in ascending
  priority and the first matching rule turns the flag on; otherwise the flag's
  `default_value` is returned.
- **Rule types:** `user`, `tenant`, `role`, `scope`, `attribute`, `environment`, and
  `percentage`, with `in`/`not_in` operators where applicable. Percentage rollouts are
  deterministic: bucket = first 8 hex chars of `sha256(flag_key . subject_key)` mod 100
  (no salt, no delimiter -- subjects are never silently rebucketed), with per-rule
  `subject` selection (`user` default, `tenant`, or `custom` via a context attribute).
- **Extension-owned contracts** (`Glueful\Extensions\Flags\Contracts`):
  `FeatureFlagCheckerInterface` (bound to the DB-backed checker by default) and
  `FeatureFlagManagerInterface` (bound to `FeatureFlagManager`). No framework-core seam.
- **Checkers:** `DatabaseFeatureFlagChecker` (default binding),
  `ConfigFeatureFlagChecker` (constant array map), and `NullFeatureFlagChecker`
  (constant result, defaults to `false` -- the deliberate opposite of the framework
  entitlement seam's absent-allow default).
- **`FlagContext` value object + `FlagContextFactory`** (`fromArray()` for payloads/CLI,
  `fromRequest()` for HTTP requests via `auth.user`, `tenant.id`, and `flags.attributes`
  request attributes).
- **`FeatureFlagManager`:** evaluate (`enabled`), `get`, `create`, `update`, `addRule`,
  `removeRule`; writes invalidate the affected flag's cache entries and record
  `feature_flag_audits` rows. Archive and rule removal are soft (status flip / rule
  disable); rows are kept for history.
- **Per-request definition cache** (`FeatureFlagCache`): in-process memoization keyed by
  flag key and environment, including negative lookups; cleared per flag on manager
  writes. No shared backend or TTL yet -- the `flags.cache_ttl` config key is reserved.
- **Management HTTP API** under `/flags` (toggle with `flags.routes_enabled`): list,
  create, show, update, archive, add rule, remove rule, and evaluate -- all behind `auth`
  plus the permission guard, with OpenAPI route annotations.
- **Permission guard:** `flags_permission` route middleware (`RequireFlagsPermission`)
  calling `PermissionManager::can()` fail-closed (no user, no manager, or denial returns
  403), and catalog registration of the `flags.view`, `flags.manage`, and
  `flags.evaluate` permissions on the `flags` resource.
- **CLI commands:** `flags:list`, `flags:get`, `flags:enable`, `flags:disable`,
  `flags:archive`, and `flags:evaluate` (`--user`, `--tenant`, `--env`).
- **Events** (PSR-14 `BaseEvent`, dispatched when an `EventService` is available):
  `FlagCreated`, `FlagUpdated`, `FlagEnabled`, `FlagDisabled`, `FlagRuleAdded`,
  `FlagRuleRemoved`.
- **Config** (`config/flags.php`): `default` (missing-flag result), `environment`
  (default for the HTTP evaluate endpoint), `routes_enabled`, plus reserved `enabled`
  and `cache_ttl` keys.

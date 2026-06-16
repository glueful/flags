# Changelog

All notable changes to `glueful/flags` will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [Unreleased]

## [1.1.1] - 2026-06-16

### Fixed

- Register migration paths during provider boot so `migrate:run` sees the feature
  flag schema through the same CLI lifecycle used by other extension migrations.

## [1.1.0] - 2026-06-14

### Changed

- Migrated OpenAPI documentation to the framework 1.57.0 reflect generator. Route
  documentation (summaries, query parameters, request-body fields and response codes)
  is now expressed as typed `#[ApiOperation]`, `#[QueryParam]` and `#[ApiResponse]`
  attributes on the controller methods; the now-inert route-file docblocks were removed.
  Docs-only — no runtime behaviour changes.
- Raised the minimum framework requirement to `^1.57.0`.

## [1.0.1] - 2026-06-13

### Security

- Record the authenticated actor UUID in feature-flag audit rows for create, update/archive, rule-add, and rule-remove operations.
- Derive `created_by` from the authenticated actor during flag creation instead of accepting a client-supplied creator UUID.
- Make `flags_permission` fail closed when a route omits the required permission parameter instead of defaulting to `flags.view`.

## [1.0.0] - 2026-06-11

First release. **Feature flags and rollout controls** for Glueful: flag definitions with
targeting rules and deterministic percentage rollouts, evaluated through an extension-owned
checker contract. Requires `glueful/framework >=1.55.0`.

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
  `feature_flag_audits` rows whose `before`/`after` columns carry **full flag/rule
  snapshots**. Archive and rule removal are soft (status flip / rule disable); rows are
  kept for history. Unknown flag keys raise `FlagNotFoundException` and unknown or
  already-removed rule UUIDs raise `RuleNotFoundException`; rule removal dispatches
  `FlagRuleRemoved` and records a `rule_removed` audit row.
- **`FlagPayloadValidator`:** structural validation for create/update/rule payloads
  (key charset/length and uniqueness, immutable key on update, status and rule-type
  whitelists, boolean toggles, percentage 0-100, subject whitelist, attribute/custom
  value shapes); update payloads are whitelisted so unknown fields are dropped.
- **Per-request definition cache** (`FeatureFlagCache`): in-process memoization keyed by
  flag key and environment, including negative lookups; cleared per flag on manager
  writes. No shared backend or TTL yet.
- **Management HTTP API** under `/flags` (toggle with `flags.routes_enabled`): list,
  create, show, update, archive, add rule, remove rule, and evaluate -- all behind `auth`
  plus the permission guard, with OpenAPI route annotations. Invalid payloads return the
  framework's 422 validation envelope (`error.details.flag` / `error.details.rule`);
  unknown flag keys and unknown or already-removed rule UUIDs return 404.
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
  (default for the HTTP evaluate endpoint), and `routes_enabled`. Every key is read by
  a code path; there are no reserved keys.
- **Provider version** derives from composer.json's `extra.glueful.version` (cached
  static read); no hardcoded version string in the provider.

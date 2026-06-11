# Flags Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `glueful/flags` as a first-party Glueful extension for feature flags, rollout rules, audience targeting, percentage rollout, audit history, CLI management, and optional admin APIs.

**Architecture:** Extension-owned contracts and services. Core receives no feature-flag seam in v1. Apps and extensions that need flags depend on `glueful/flags` directly and resolve `Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface`. The extension exposes a small check API, persists flag/rule/audit rows, evaluates deterministically, and protects management endpoints with an extension-owned permission guard.

**Tech Stack:** PHP 8.3+, Glueful Framework 1.54.0+, PHPUnit 10.5, PHPStan level 6, Glueful extension `ServiceProvider`, Glueful container DSL, Glueful migrations, Glueful `PermissionManager`, Glueful events, SQLite/temp database for repository tests.

**Spec:** `docs/specs/2026-06-11-flags-design.md` (read it first).

**Conventions used throughout:**
- Namespace `Glueful\Extensions\Flags\` maps to `src/`; tests namespace `Glueful\Extensions\Flags\Tests\` maps to `tests/`.
- Run commands from `/Users/michaeltawiahsowah/Sites/glueful/extensions/flags`.
- Use `Glueful\Extensions\Flags\FlagsServiceProvider` as the extension provider.
- Do not add framework-core contracts in v1.
- Do not hard depend on users or tenancy tables.
- Every implementation task is red/green: write the named failing test, run the exact `--filter`, implement the smallest passing code, rerun the same filter, then commit.
- Put controllers, guards, middleware, managers, repositories, and command aliases in `FlagsServiceProvider::services()`. The container compiles before `boot()`, so `boot()` is only for loading routes, migrations, and commands.
- Do not introduce a percentage rollout salt. The locked deterministic formula is `flag_key + subject_key`; a salt rebuckets users and violates the spec.
- Permission guard tests must cover the four failure/success cases named in Task 8.

---

## File Structure

- `composer.json`: package metadata, one PSR-4 root, `autoload.classmap: ["migrations/"]`, `extra.glueful`, scripts.
- `phpunit.xml`: Unit and Integration suites.
- `phpstan.neon`: level 6.
- `CHANGELOG.md`: `0.1.0` entry.
- `config/flags.php`: `enabled`, `default`, `cache_ttl`, `environment`, route toggle. No rollout salt.
- `migrations/001_CreateFeatureFlagsTables.php`: flags/rules/audits.
- `src/Contracts/*`: `FeatureFlagCheckerInterface`, `FeatureFlagManagerInterface`.
- `src/Models/*`: `FeatureFlag`, `FeatureFlagRule`.
- `src/Support/*`: context, factory, rule matcher, cache keys.
- `src/Repositories/*`: flag and audit persistence.
- `src/Services/*`: null/config/database checkers, evaluator, manager, cache.
- `src/Events/*`: flag lifecycle events.
- `src/Http/*`: permission guard and controllers.
- `src/Console/*`: `flags:list`, `flags:get`, `flags:enable`, `flags:disable`, `flags:evaluate`.
- `tests/Support/FlagsTestCase.php`: in-memory SQLite harness, migrations, tiny container.

---

### Task 1: Package Scaffold, Tooling, And Test Harness

**Files:**
- Create or update: `composer.json`
- Create: `phpunit.xml`
- Create: `phpstan.neon`
- Create: `tests/bootstrap.php`
- Create: `tests/Support/FlagsTestCase.php`
- Create: `CHANGELOG.md`
- Review existing: `.gitignore`

- [ ] Create `composer.json` with package name `glueful/flags`, type `glueful-extension`, require php only, require-dev `glueful/framework:^1.54.0`, `phpunit/phpunit:^10.5`, `squizlabs/php_codesniffer:^3.6`, `phpstan/phpstan:^1.0`, PSR-4 autoload for `Glueful\Extensions\Flags\`, `autoload.classmap: ["migrations/"]`, and `extra.glueful` provider `Glueful\Extensions\Flags\FlagsServiceProvider`, version `0.1.0`, requires `{"glueful": ">=1.54.0", "extensions": []}`.
- [ ] Create `phpunit.xml` with Unit (`tests/Unit`) and Integration (`tests/Integration`) suites and `tests/bootstrap.php` as bootstrap.
- [ ] Create `phpstan.neon` with `level: 6`, `paths: [src]`, and bootstrap through Composer autoload if needed.
- [ ] Create `tests/bootstrap.php` requiring `vendor/autoload.php`.
- [ ] Create `tests/Support/FlagsTestCase.php` modeled on `glueful/subscriptions`' SQLite harness: create a `Glueful\Database\Connection` against in-memory SQLite, run `CreateFeatureFlagsTables` once it exists, expose `connection()` and `appContext()`, and provide `seedFlag(array $overrides = []): array`.
- [ ] Preserve the existing `.gitignore` if present; only add missing ignores for `/vendor/`, `/composer.lock`, `.phpunit.cache/`, and `.DS_Store`.
- [ ] Create `CHANGELOG.md` with an Unreleased section and an initial `0.1.0` planning entry.
- [ ] Run `composer install`.
- [ ] Run `vendor/bin/phpunit --filter=FlagsTestCase`.
- [ ] Expected: FAIL until migration class exists; keep this known failure for Task 3.
- [ ] Commit: `git add composer.json phpunit.xml phpstan.neon tests/bootstrap.php tests/Support/FlagsTestCase.php CHANGELOG.md .gitignore && git commit -m "chore: scaffold flags extension tooling"`

---

### Task 2: Contracts And Context Value Object

**Files:**
- Create: `src/Contracts/FeatureFlagCheckerInterface.php`
- Create: `src/Contracts/FeatureFlagManagerInterface.php`
- Create: `src/Models/FeatureFlag.php`
- Create: `src/Models/FeatureFlagRule.php`
- Create: `src/Support/FlagContext.php`
- Create: `src/Support/FlagContextFactory.php`
- Test: `tests/Support/FlagContextTest.php`
- Test: `tests/Support/FlagContextFactoryTest.php`

- [ ] Write failing `FlagContextTest` for immutable user/tenant/environment/roles/scopes/attributes and `subjectKey()` behavior.
- [ ] Run `vendor/bin/phpunit --filter=FlagContextTest`.
- [ ] Expected: FAIL because classes are missing.
- [ ] Define `FeatureFlagCheckerInterface::enabled(string $flag, FlagContext $context): bool`.
- [ ] Define `FeatureFlagManagerInterface extends FeatureFlagCheckerInterface` with `get()`, `create()`, `update()`, `addRule()`, and `removeRule()` exactly as the spec public API.
- [ ] Implement readonly `FeatureFlag` and `FeatureFlagRule` model/value objects used by repository, manager, routes, and tests.
- [ ] Implement immutable `FlagContext` with nullable `userUuid`, `tenantUuid`, `environment`, arrays for `roles`, `scopes`, and `attributes`.
- [ ] Add helpers on `FlagContext` for attribute lookup and rollout subject lookup: `subjectKey(string $subject, ?string $attributeKey = null): ?string`.
- [ ] Implement `FlagContextFactory` for explicit arrays first. HTTP/queue/CLI extraction can be added through optional methods without coupling to users or tenancy.
- [ ] Unit test that context values are immutable and normalize roles/scopes to unique string arrays.
- [ ] Unit test that `subjectKey('user')`, `subjectKey('tenant')`, and `subjectKey('custom', 'account')` return the correct values and return `null` when unavailable.
- [ ] Run `vendor/bin/phpunit --filter='FlagContextTest|FlagContextFactoryTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add src/Contracts src/Models src/Support tests/Support && git commit -m "feat(flags): add public contracts and flag context"`

---

### Task 3: Database Migrations And Repositories

**Files:**
- Create: `migrations/001_CreateFeatureFlagsTables.php`
- Create: `src/Repositories/FeatureFlagRepository.php`
- Create: `src/Repositories/FeatureFlagAuditRepository.php`
- Test: `tests/Repositories/FeatureFlagRepositoryTest.php`

- [ ] Write failing `tests/Integration/MigrationsTest.php::testFeatureFlagTablesExist` using `FlagsTestCase`.
- [ ] Run `vendor/bin/phpunit --filter=MigrationsTest`.
- [ ] Expected: FAIL because `CreateFeatureFlagsTables` is missing.
- [ ] Add migrations for `feature_flags`, `feature_flag_rules`, and `feature_flag_audits` exactly as defined in the spec.
- [ ] Use `uuid` string columns for cross-row references; do not create database foreign keys to users, tenants, or extension tables.
- [ ] Update `FlagsTestCase` to run `CreateFeatureFlagsTables`.
- [ ] Run `vendor/bin/phpunit --filter=MigrationsTest`.
- [ ] Expected: PASS.
- [ ] Write failing `FeatureFlagRepositoryTest` for create/load/update/archive, globally duplicate `key` rejection, rule ordering by priority, and missing flag returning `null`.
- [ ] Run `vendor/bin/phpunit --filter=FeatureFlagRepositoryTest`.
- [ ] Expected: FAIL because repository is missing.
- [ ] Implement repository methods for loading a flag with enabled rules ordered by priority, creating/updating flags, archiving flags, and writing audit rows.
- [ ] Keep allowed lifecycle statuses constrained in code to `active` and `archived`.
- [ ] Test create/load/update/archive behavior against a temp database.
- [ ] Test that missing flags return `null` from the repository instead of throwing.
- [ ] Run `vendor/bin/phpunit --filter='MigrationsTest|FeatureFlagRepositoryTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add migrations src/Repositories tests/Integration tests/Support && git commit -m "feat(flags): add schema and flag repositories"`

---

### Task 4: Evaluation Engine

**Files:**
- Create: `src/Services/NullFeatureFlagChecker.php`
- Create: `src/Services/ConfigFeatureFlagChecker.php`
- Create: `src/Services/DatabaseFeatureFlagChecker.php`
- Create: `src/Services/FeatureFlagEvaluator.php`
- Create: `src/Support/RuleMatcher.php`
- Test: `tests/Services/FeatureFlagEvaluatorTest.php`
- Test: `tests/Services/DatabaseFeatureFlagCheckerTest.php`

- [ ] Write failing `FeatureFlagEvaluatorTest` for missing default, disabled false, priority, user/tenant/role/scope/attribute rules, environment rule, and stable percentage rollout from `flag_key + subject_key`.
- [ ] Run `vendor/bin/phpunit --filter=FeatureFlagEvaluatorTest`.
- [ ] Expected: FAIL because services are missing.
- [ ] Implement `NullFeatureFlagChecker` with fail-closed default `false`, configurable only through constructor/config.
- [ ] Implement `ConfigFeatureFlagChecker` for simple app-level flags from config.
- [ ] Implement `FeatureFlagEvaluator` with the spec order: missing flag default, disabled flag false, enabled rules by priority, first decisive match, then `default_value`.
- [ ] Support rule types `user`, `tenant`, `role`, `scope`, `attribute`, `percentage`, and `environment`.
- [ ] Implement stable percentage rollout by hashing `flag_key + subject_key`; default subject is `user`, supported subjects are `user`, `tenant`, and `custom`.
- [ ] Unit test disabled flags always return false.
- [ ] Unit test first decisive rule wins by priority.
- [ ] Unit test percentage rollout is stable for the same flag/subject and changes when the subject changes.
- [ ] Unit test absent checker/missing flag behavior fails closed unless an explicit default is configured.
- [ ] Run `vendor/bin/phpunit --filter='FeatureFlagEvaluatorTest|DatabaseFeatureFlagCheckerTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add src/Services src/Support tests/Services && git commit -m "feat(flags): evaluate rollout rules deterministically"`

---

### Task 5: Manager API, Caching, And Invalidation

**Files:**
- Create: `src/Services/FeatureFlagManager.php`
- Create: `src/Services/FeatureFlagCache.php`
- Test: `tests/Services/FeatureFlagManagerTest.php`
- Test: `tests/Services/FeatureFlagCacheTest.php`

- [ ] Write failing `FeatureFlagManagerTest` for `get`, `create`, `update`, `addRule`, `removeRule`, and `enabled` delegating to the evaluator.
- [ ] Write failing `FeatureFlagCacheTest` proving flag definitions are cached by key and environment, update clears a single flag entry, and bulk clear removes all flag entries.
- [ ] Run `vendor/bin/phpunit --filter='FeatureFlagManagerTest|FeatureFlagCacheTest'`.
- [ ] Expected: FAIL because manager/cache are missing.
- [ ] Implement `FeatureFlagManager implements FeatureFlagManagerInterface`.
- [ ] Implement `FeatureFlagCache` for definition caching only. Do not cache evaluation results unless a future context key is explicit and stable.
- [ ] Wire manager updates to invalidate the affected flag cache entry.
- [ ] Run `vendor/bin/phpunit --filter='FeatureFlagManagerTest|FeatureFlagCacheTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add src/Services tests/Services && git commit -m "feat(flags): add manager API and definition cache"`

---

### Task 6: Events

**Files:**
- Create: `src/Events/FlagCreated.php`
- Create: `src/Events/FlagUpdated.php`
- Create: `src/Events/FlagEnabled.php`
- Create: `src/Events/FlagDisabled.php`
- Create: `src/Events/FlagRuleAdded.php`
- Create: `src/Events/FlagRuleRemoved.php`
- Test: `tests/Events/FlagEventsTest.php`
- Test: `tests/Services/FeatureFlagManagerEventsTest.php`

- [ ] Write failing `FlagEventsTest` asserting every event extends `Glueful\Events\Contracts\BaseEvent` and exposes flag key/uuid plus relevant rule uuid/action data.
- [ ] Write failing `FeatureFlagManagerEventsTest` with a fake `EventService` proving create/update/enable/disable/add-rule/remove-rule dispatch the matching event.
- [ ] Run `vendor/bin/phpunit --filter='FlagEventsTest|FeatureFlagManagerEventsTest'`.
- [ ] Expected: FAIL because events are missing.
- [ ] Implement the six event classes and dispatch from `FeatureFlagManager`.
- [ ] Run `vendor/bin/phpunit --filter='FlagEventsTest|FeatureFlagManagerEventsTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add src/Events src/Services tests/Events tests/Services && git commit -m "feat(flags): emit flag lifecycle events"`

---

### Task 7: Service Provider, Config, Permissions, And Guards

**Files:**
- Create: `src/FlagsServiceProvider.php`
- Create: `config/flags.php`
- Create: `src/Http/RequireFlagsPermission.php`
- Test: `tests/FlagsServiceProviderTest.php`
- Test: `tests/Http/RequireFlagsPermissionTest.php`

- [ ] Write failing `FlagsServiceProviderTest` asserting services include aliases/bindings for `FeatureFlagCheckerInterface`, `FeatureFlagManagerInterface`, controllers, commands, guard, and cache before `boot()` runs.
- [ ] Write failing guard tests named `testPermissionMiddlewareReturns403WithoutAuthenticatedUser`, `testPermissionMiddlewareReturns403WhenManagerUnavailable`, `testPermissionMiddlewareReturns403WithRealManagerAndNoProvider`, `testPermissionMiddlewareReturns403WhenPermissionDenied`, and `testPermissionMiddlewareCallsNextOnlyWhenAllowed`.
- [ ] Run `vendor/bin/phpunit --filter='FlagsServiceProviderTest|RequireFlagsPermissionTest'`.
- [ ] Expected: FAIL because provider/guard wiring is missing.
- [ ] Implement `FlagsServiceProvider extends Glueful\Extensions\ServiceProvider`.
- [ ] Register config defaults for enabled flag, missing-flag default, route enablement, cache TTL, and environment. Do not add percentage rollout salt.
- [ ] Register services using `services()` and bind `FeatureFlagCheckerInterface` to `DatabaseFeatureFlagChecker` by default.
- [ ] Register controllers, guard/middleware, and console commands in `services()`; do not register these in `boot()`.
- [ ] Register migrations with source `glueful/flags`.
- [ ] Declare permissions through `permissions()` for flag read/write/archive/audit access.
- [ ] Implement an extension-owned guard/middleware that calls `Glueful\Permissions\PermissionManager::can()` directly for management routes.
- [ ] Unit test that the provider exposes the checker binding and declares expected permissions.
- [ ] Unit test that permission denial blocks management endpoints even if permissions are only declarative elsewhere.
- [ ] Run `vendor/bin/phpunit --filter='FlagsServiceProviderTest|RequireFlagsPermissionTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add config src/FlagsServiceProvider.php src/Http tests && git commit -m "feat(flags): wire provider and permission guard"`

---

### Task 8: HTTP API And CLI Management

**Files:**
- Create: `routes/routes.php`
- Create: `src/Http/Controllers/FeatureFlagController.php`
- Create: `src/Http/Controllers/FeatureFlagRuleController.php`
- Create: `src/Http/Controllers/FeatureFlagEvaluateController.php`
- Create: `src/Console/FlagListCommand.php`
- Create: `src/Console/FlagGetCommand.php`
- Create: `src/Console/FlagEnableCommand.php`
- Create: `src/Console/FlagDisableCommand.php`
- Create: `src/Console/FlagEvaluateCommand.php`
- Create: `src/Console/FlagArchiveCommand.php`
- Test: `tests/Http/FeatureFlagControllerTest.php`
- Test: `tests/Http/FeatureFlagRuleControllerTest.php`
- Test: `tests/Http/FeatureFlagEvaluateControllerTest.php`
- Test: `tests/Console/FlagCommandsTest.php`

- [ ] Write failing controller tests for all locked routes: `GET /flags`, `POST /flags`, `GET /flags/{key}`, `PATCH /flags/{key}`, `DELETE /flags/{key}`, `POST /flags/{key}/rules`, `DELETE /flags/{key}/rules/{uuid}`, and `POST /flags/{key}/evaluate`.
- [ ] Write failing command tests for `flags:list`, `flags:get`, `flags:enable`, `flags:disable`, and `flags:evaluate <key> [--user=] [--tenant=] [--env=]`.
- [ ] Run `vendor/bin/phpunit --filter='FeatureFlagControllerTest|FeatureFlagRuleControllerTest|FeatureFlagEvaluateControllerTest|FlagCommandsTest'`.
- [ ] Expected: FAIL because routes/controllers/commands are missing.
- [ ] Add routes for listing flags, reading a flag, creating/updating a flag, archiving a flag, and reading audit history.
- [ ] Add rule routes for adding/removing rules.
- [ ] Add evaluate route and ensure it builds `FlagContext` from request payload/options.
- [ ] Keep evaluation checks available through service API; do not require HTTP for app usage.
- [ ] Ensure management routes are optional/config-gated if the host app does not want an admin API.
- [ ] Emit audit rows for create/update/enable/disable/archive actions with before/after payloads.
- [ ] Add CLI commands for list, get, enable, disable, archive, and evaluate.
- [ ] Test successful API mutations create audit rows.
- [ ] Test guard failure returns an authorization error.
- [ ] Run `vendor/bin/phpunit --filter='FeatureFlagControllerTest|FeatureFlagRuleControllerTest|FeatureFlagEvaluateControllerTest|FlagCommandsTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add routes src/Http/Controllers src/Console tests && git commit -m "feat(flags): add admin API and CLI"`

---

### Task 9: Documentation And Verification

**Files:**
- Update: `README.md`
- Create: `docs/usage.md`
- Create: `docs/rules.md`
- Update: `CHANGELOG.md`

- [ ] Document service usage with `$flags->enabled('new_editor', $context)`.
- [ ] Document rule types, percentage rollout subjects, fail-closed behavior, and the difference between flags and entitlements.
- [ ] Document provider registration and management routes.
- [ ] Document cache invalidation behavior and event names.
- [ ] Update `CHANGELOG.md` with a `0.1.0` Added section.
- [ ] Run `composer validate --strict`.
- [ ] Run `vendor/bin/phpunit`.
- [ ] Run `vendor/bin/phpstan analyse src --level=6`.
- [ ] Run `vendor/bin/phpcs --standard=PSR12 src` if phpcs is installed.
- [ ] Commit: `git add README.md docs CHANGELOG.md && git commit -m "docs(flags): document usage, rules, cache, and events"`

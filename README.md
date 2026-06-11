# Glueful Flags

Feature flags and rollout controls for Glueful apps. Define flags, target
audiences, staged rollouts, and evaluate feature availability through an
extension-owned checker contract.

## Usage

```php
$flags = app($context, \Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface::class);
$enabled = $flags->enabled('new_editor', new FlagContext(userUuid: $userUuid));
```

Flags fail closed by default: missing checker, missing flag, archived flag, or
disabled flag evaluates to `false` unless an explicit missing-flag default is
configured.

## Rules

Supported rule types:

- `user`
- `tenant`
- `role`
- `scope`
- `attribute`
- `percentage`
- `environment`

Percentage rollout is deterministic and uses `flag_key + subject_key`. There is
no rollout salt, so users are not silently rebucketed by config changes.

## Commands

- `flags:list`
- `flags:get <key>`
- `flags:enable <key>`
- `flags:disable <key>`
- `flags:archive <key>`
- `flags:evaluate <key> [--user=] [--tenant=] [--env=]`

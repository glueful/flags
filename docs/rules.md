# Flag Rules

Evaluation order:

1. Missing flag returns the configured default.
2. Disabled or archived flag returns `false`.
3. Enabled rules run by priority.
4. First matching rule enables the flag.
5. If no rule matches, the flag's `default_value` is returned.

Percentage rules are stable:

- hash input is `flag_key + subject_key`;
- subject is `user`, `tenant`, or `custom`;
- `custom` requires an attribute key;
- no random values and no rollout salt are used.

Events emitted by the manager:

- `FlagCreated`
- `FlagUpdated`
- `FlagEnabled`
- `FlagDisabled`
- `FlagRuleAdded`
- `FlagRuleRemoved`

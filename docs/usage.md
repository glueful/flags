# Flags Usage

Use `glueful/flags` when code needs to ask whether behavior is rolled out for a
runtime context. Do not use it for commercial access control; use entitlements
or subscriptions for that.

```php
$context = new \Glueful\Extensions\Flags\Support\FlagContext(
    userUuid: 'user-1',
    tenantUuid: 'tenant-1',
    environment: 'production',
    roles: ['admin'],
);

$flags->enabled('new_editor', $context);
```

Flag definitions are cached by key and environment. Manager writes clear the
affected flag definition; evaluation results are not cached.

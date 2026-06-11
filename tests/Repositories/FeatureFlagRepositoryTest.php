<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Repositories;

use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;

final class FeatureFlagRepositoryTest extends FlagsTestCase
{
    public function testCreateLoadUpdateArchiveAndRules(): void
    {
        $repo = new FeatureFlagRepository($this->connection());
        $flag = $repo->create(['key' => 'new_editor', 'name' => 'New editor', 'enabled' => true]);
        $rule = $repo->addRule('new_editor', ['type' => 'role', 'operator' => 'in', 'value' => ['admin']]);

        self::assertSame('new_editor', $repo->find('new_editor')?->key);
        self::assertSame($rule->uuid, $repo->find('new_editor')?->rules[0]->uuid);

        $updated = $repo->update('new_editor', ['default_value' => true]);
        self::assertTrue($updated->defaultValue);
        self::assertSame('archived', $repo->archive('new_editor')->status);
    }
}

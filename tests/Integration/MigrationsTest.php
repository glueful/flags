<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Integration;

use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;

final class MigrationsTest extends FlagsTestCase
{
    public function testFeatureFlagTablesExist(): void
    {
        self::assertTrue($this->connection()->getSchemaBuilder()->hasTable('feature_flags'));
        self::assertTrue($this->connection()->getSchemaBuilder()->hasTable('feature_flag_rules'));
        self::assertTrue($this->connection()->getSchemaBuilder()->hasTable('feature_flag_audits'));
    }
}

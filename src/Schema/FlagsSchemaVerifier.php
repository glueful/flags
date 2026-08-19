<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Schema;

use Glueful\Database\Connection;
use Glueful\Extensions\Schema\StructuralVerifierInterface;

/**
 * Structural verifier for glueful/flags (schema policy spec B7): each create migration proves
 * every table it creates with its load-bearing columns. Unknown basenames are never adoptable.
 */
final class FlagsSchemaVerifier implements StructuralVerifierInterface
{
    public function source(): string
    {
        return 'glueful/flags';
    }

    /** @return list<string> */
    public function migrationBasenames(): array
    {
        return [
            '001_CreateFeatureFlagsTables.php',
        ];
    }

    public function verify(Connection $db, string $migrationBasename): bool
    {
        return match ($migrationBasename) {
            '001_CreateFeatureFlagsTables.php' => $this->tablesWithColumns($db, [
                'feature_flags' => ['key', 'enabled', 'default_value'],
                'feature_flag_rules' => ['flag_uuid', 'type', 'operator'],
                'feature_flag_audits' => ['flag_uuid', 'action'],
            ]),
            default => false,
        };
    }

    /** @param array<string, list<string>> $expectations */
    private function tablesWithColumns(Connection $db, array $expectations): bool
    {
        $schema = $db->getSchemaBuilder();
        foreach ($expectations as $table => $columns) {
            if (!$schema->hasTable($table)) {
                return false;
            }
            foreach ($columns as $column) {
                if (!$schema->hasColumn($table, $column)) {
                    return false;
                }
            }
        }
        return true;
    }
}

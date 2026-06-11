<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Repositories;

use Glueful\Database\Connection;
use Glueful\Extensions\Flags\Models\FeatureFlag;
use Glueful\Extensions\Flags\Models\FeatureFlagRule;
use Glueful\Helpers\Utils;

final class FeatureFlagRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $key): ?FeatureFlag
    {
        $row = $this->connection->table('feature_flags')->where('key', '=', $key)->first();
        if ($row === null) {
            return null;
        }

        return $this->hydrateFlag($row, $this->rulesFor((string) $row['uuid']));
    }

    /** @return list<FeatureFlag> */
    public function all(): array
    {
        return array_map(
            fn(array $row): FeatureFlag => $this->hydrateFlag($row, $this->rulesFor((string) $row['uuid'])),
            $this->connection->table('feature_flags')->orderBy('key', 'ASC')->get()
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): FeatureFlag
    {
        $now = $this->now();
        $row = [
            'uuid' => Utils::generateNanoID(12),
            'key' => $this->string($data, 'key'),
            'name' => (string) ($data['name'] ?? $data['key']),
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'enabled' => (bool) ($data['enabled'] ?? false),
            'default_value' => (bool) ($data['default_value'] ?? false),
            'status' => $this->status((string) ($data['status'] ?? 'active')),
            'created_by' => isset($data['created_by']) ? (string) $data['created_by'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->connection->table('feature_flags')->insert($row);

        return $this->hydrateFlag($row, []);
    }

    /** @param array<string,mixed> $data */
    public function update(string $key, array $data): FeatureFlag
    {
        if (isset($data['status'])) {
            $data['status'] = $this->status((string) $data['status']);
        }
        if (array_key_exists('default_value', $data)) {
            $data['default_value'] = (bool) $data['default_value'];
        }
        if (array_key_exists('enabled', $data)) {
            $data['enabled'] = (bool) $data['enabled'];
        }
        $data['updated_at'] = $this->now();

        $this->connection->table('feature_flags')->where('key', '=', $key)->update($data);
        $flag = $this->find($key);
        if ($flag === null) {
            throw new \RuntimeException(sprintf('Feature flag "%s" was not found.', $key));
        }

        return $flag;
    }

    public function archive(string $key): FeatureFlag
    {
        return $this->update($key, ['status' => 'archived', 'enabled' => false]);
    }

    /** @param array<string,mixed> $data */
    public function addRule(string $flagKey, array $data): FeatureFlagRule
    {
        $flag = $this->find($flagKey);
        if ($flag === null) {
            throw new \RuntimeException(sprintf('Feature flag "%s" was not found.', $flagKey));
        }

        $now = $this->now();
        $row = [
            'uuid' => Utils::generateNanoID(12),
            'flag_uuid' => $flag->uuid,
            'priority' => (int) ($data['priority'] ?? 0),
            'type' => $this->string($data, 'type'),
            'operator' => (string) ($data['operator'] ?? 'in'),
            'value' => json_encode($data['value'] ?? null, JSON_THROW_ON_ERROR),
            'percentage' => isset($data['percentage']) ? (int) $data['percentage'] : null,
            'subject' => isset($data['subject']) ? (string) $data['subject'] : null,
            'enabled' => (bool) ($data['enabled'] ?? true),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->connection->table('feature_flag_rules')->insert($row);

        return $this->hydrateRule($row);
    }

    public function removeRule(string $flagKey, string $ruleUuid): void
    {
        $flag = $this->find($flagKey);
        if ($flag === null) {
            throw new \RuntimeException(sprintf('Feature flag "%s" was not found.', $flagKey));
        }

        $this->connection
            ->table('feature_flag_rules')
            ->where('flag_uuid', '=', $flag->uuid)
            ->where('uuid', '=', $ruleUuid)
            ->update(['enabled' => false, 'updated_at' => $this->now()]);
    }

    /** @return list<FeatureFlagRule> */
    private function rulesFor(string $flagUuid): array
    {
        return array_map(
            fn(array $row): FeatureFlagRule => $this->hydrateRule($row),
            $this->connection
                ->table('feature_flag_rules')
                ->where('flag_uuid', '=', $flagUuid)
                ->where('enabled', '=', true)
                ->orderBy('priority', 'ASC')
                ->get()
        );
    }

    /**
     * @param array<string,mixed> $row
     * @param list<FeatureFlagRule> $rules
     */
    private function hydrateFlag(array $row, array $rules): FeatureFlag
    {
        return new FeatureFlag(
            (string) $row['uuid'],
            (string) $row['key'],
            (string) $row['name'],
            isset($row['description']) ? (string) $row['description'] : null,
            (bool) $row['enabled'],
            (bool) $row['default_value'],
            (string) $row['status'],
            isset($row['created_by']) ? (string) $row['created_by'] : null,
            $rules
        );
    }

    /** @param array<string,mixed> $row */
    private function hydrateRule(array $row): FeatureFlagRule
    {
        $value = is_string($row['value'] ?? null) && $row['value'] !== ''
            ? json_decode((string) $row['value'], true)
            : null;

        return new FeatureFlagRule(
            (string) $row['uuid'],
            (string) $row['flag_uuid'],
            (int) $row['priority'],
            (string) $row['type'],
            (string) $row['operator'],
            $value,
            isset($row['percentage']) ? (int) $row['percentage'] : null,
            isset($row['subject']) ? (string) $row['subject'] : null,
            (bool) $row['enabled']
        );
    }

    /** @param array<string,mixed> $data */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_scalar($value) || (string) $value === '') {
            throw new \InvalidArgumentException(sprintf('"%s" is required.', $key));
        }

        return (string) $value;
    }

    private function status(string $status): string
    {
        if (!in_array($status, ['active', 'archived'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid flag status "%s".', $status));
        }

        return $status;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

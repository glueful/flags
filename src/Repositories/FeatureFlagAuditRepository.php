<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Repositories;

use Glueful\Database\Connection;
use Glueful\Helpers\Utils;

final class FeatureFlagAuditRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public function record(
        string $flagUuid,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?string $actor = null
    ): void {
        $this->connection->table('feature_flag_audits')->insert([
            'uuid' => Utils::generateNanoID(12),
            'flag_uuid' => $flagUuid,
            'action' => $action,
            'before' => $before !== null ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after' => $after !== null ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'actor_uuid' => $actor,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function forFlag(string $flagUuid): array
    {
        return $this->connection
            ->table('feature_flag_audits')
            ->where('flag_uuid', '=', $flagUuid)
            ->orderBy('id', 'DESC')
            ->get();
    }
}

<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_agent_profiles (extended profile data).
 */
class AgentProfileRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_agent_profiles';
    }

    public function findByMlsId(string $agentMlsId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE agent_mls_id = %s LIMIT 1",
                $agentMlsId
            )
        );

        return $result ?: null;
    }

    public function findByUserId(int $userId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d LIMIT 1",
                $userId
            )
        );

        return $result ?: null;
    }

    /**
     * Insert or update a profile by agent_mls_id.
     *
     * @return int|false Profile ID on success, false on failure.
     */
    public function upsert(string $agentMlsId, array $data): int|false
    {
        $existing = $this->findByMlsId($agentMlsId);

        if ($existing !== null) {
            $this->update((int) $existing->id, $data);
            return (int) $existing->id;
        }

        $data['agent_mls_id'] = $agentMlsId;
        return $this->create($data);
    }

    /**
     * @return object[]
     */
    public function findFeatured(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table}
             WHERE is_featured = 1 AND is_active = 1
             ORDER BY display_order ASC, created_at ASC"
        ) ?? [];
    }

    /**
     * @return object[]
     */
    public function findActive(int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY display_order ASC, created_at ASC";

        if ($limit > 0) {
            $sql = $this->wpdb->prepare(
                $sql . ' LIMIT %d OFFSET %d',
                $limit,
                $offset
            );
        }

        return $this->wpdb->get_results($sql) ?? [];
    }

    public function countActive(): int
    {
        return (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1"
        );
    }
}

<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_agent_activity_log.
 */
class ActivityLogRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_agent_activity_log';
    }

    /** This table only has created_at, no updated_at. */
    protected bool $timestamps = false;

    public function create(array $data): int|false
    {
        $data['created_at'] = $data['created_at'] ?? current_time('mysql');

        // Encode metadata as JSON if provided as array.
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        $result = $this->wpdb->insert($this->table, $data);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Get activity feed for an agent (all clients).
     *
     * @return object[]
     */
    public function findByAgent(int $agentUserId, int $limit = 50, int $offset = 0): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE agent_user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $agentUserId,
                $limit,
                $offset
            )
        ) ?? [];
    }

    /**
     * Get activity for a specific client (within an agent's scope).
     *
     * @return object[]
     */
    public function findByAgentAndClient(int $agentUserId, int $clientUserId, int $limit = 50): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE agent_user_id = %d AND client_user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $agentUserId,
                $clientUserId,
                $limit
            )
        ) ?? [];
    }

    /**
     * Count distinct active clients in the last N days.
     */
    public function countActiveClients(int $agentUserId, int $days = 30): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days", (int) current_time('timestamp')));

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT client_user_id) FROM {$this->table}
                 WHERE agent_user_id = %d AND created_at >= %s",
                $agentUserId,
                $since
            )
        );
    }

    /**
     * Count activities by type for an agent.
     *
     * @return array<string, int>
     */
    public function countByType(int $agentUserId, int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days", (int) current_time('timestamp')));

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT activity_type, COUNT(*) as cnt
                 FROM {$this->table}
                 WHERE agent_user_id = %d AND created_at >= %s
                 GROUP BY activity_type",
                $agentUserId,
                $since
            )
        );

        $counts = [];
        foreach ($results ?? [] as $row) {
            $counts[$row->activity_type] = (int) $row->cnt;
        }

        return $counts;
    }

    /**
     * Count total activities for an agent in the last N days.
     */
    public function countRecent(int $agentUserId, int $days = 30): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days", (int) current_time('timestamp')));

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE agent_user_id = %d AND created_at >= %s",
                $agentUserId,
                $since
            )
        );
    }
}

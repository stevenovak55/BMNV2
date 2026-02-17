<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_referral_signups.
 */
class ReferralSignupRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_referral_signups';
    }

    /** This table only has created_at, no updated_at. */
    protected bool $timestamps = false;

    public function create(array $data): int|false
    {
        $data['created_at'] = $data['created_at'] ?? current_time('mysql');

        $result = $this->wpdb->insert($this->table, $data);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Find signup record for a client.
     */
    public function findByClient(int $clientUserId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE client_user_id = %d LIMIT 1",
                $clientUserId
            )
        );

        return $result ?: null;
    }

    /**
     * Count total signups for an agent.
     */
    public function countByAgent(int $agentUserId): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE agent_user_id = %d",
                $agentUserId
            )
        );
    }

    /**
     * Count signups for an agent this month.
     */
    public function countByAgentThisMonth(int $agentUserId): int
    {
        $firstOfMonth = date('Y-m-01', (int) current_time('timestamp'));

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE agent_user_id = %d AND created_at >= %s",
                $agentUserId,
                $firstOfMonth
            )
        );
    }

    /**
     * Count signups by source for an agent.
     *
     * @return array<string, int>
     */
    public function countBySource(int $agentUserId): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT signup_source, COUNT(*) as cnt
                 FROM {$this->table}
                 WHERE agent_user_id = %d
                 GROUP BY signup_source",
                $agentUserId
            )
        );

        $counts = [];
        foreach ($results ?? [] as $row) {
            $counts[$row->signup_source] = (int) $row->cnt;
        }

        return $counts;
    }
}

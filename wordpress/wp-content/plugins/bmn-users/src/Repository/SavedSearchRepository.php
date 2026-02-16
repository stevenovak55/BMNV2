<?php

declare(strict_types=1);

namespace BMN\Users\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for saved searches (bmn_user_saved_searches table).
 */
class SavedSearchRepository extends Repository
{
    protected bool $timestamps = true;

    protected function getTableName(): string
    {
        return 'bmn_user_saved_searches';
    }

    /**
     * @return object[]
     */
    public function findByUser(int $userId): array
    {
        return $this->findBy(
            ['user_id' => $userId],
            orderBy: 'created_at',
            order: 'DESC',
        );
    }

    public function countByUser(int $userId): int
    {
        return $this->count(['user_id' => $userId]);
    }

    /**
     * Find all active searches eligible for alert processing.
     *
     * @return object[]
     */
    public function findActiveForAlerts(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY last_alert_at ASC";

        return $this->wpdb->get_results($sql) ?? [];
    }

    public function updateAlertTimestamp(int $id, int $resultCount, int $newCount): bool
    {
        return $this->update($id, [
            'last_alert_at' => current_time('mysql'),
            'result_count'  => $resultCount,
            'new_count'     => $newCount,
        ]);
    }

    public function removeAllForUser(int $userId): int
    {
        $result = $this->wpdb->delete($this->table, ['user_id' => $userId]);

        return $result !== false ? (int) $result : 0;
    }
}

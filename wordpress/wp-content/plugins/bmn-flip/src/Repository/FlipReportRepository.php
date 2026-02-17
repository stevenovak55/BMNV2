<?php

declare(strict_types=1);

namespace BMN\Flip\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_flip_reports table.
 */
class FlipReportRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_flip_reports';
    }

    /**
     * JSON-encode structured fields before creating.
     */
    public function create(array $data): int|false
    {
        $jsonFields = ['cities', 'filters'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        return parent::create($data);
    }

    /**
     * Get paginated reports for a user, ordered by most recently updated.
     *
     * @return object[]
     */
    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $userId,
                $limit,
                $offset
            )
        ) ?? [];
    }

    /**
     * Count total reports for a user.
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d",
                $userId
            )
        );
    }

    /**
     * Find all active monitor-type reports.
     *
     * @return object[]
     */
    public function findActiveMonitors(): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE type = %s AND status = %s",
                'monitor',
                'active'
            )
        ) ?? [];
    }

    /**
     * Toggle the is_favorite flag (flip 0 to 1, 1 to 0).
     */
    public function toggleFavorite(int $id): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET is_favorite = 1 - is_favorite, updated_at = %s WHERE id = %d",
                current_time('mysql'),
                $id
            )
        );

        return $result !== false;
    }

    /**
     * Increment the run count and update property/viable counts and last run date.
     */
    public function incrementRunCount(int $id, int $propertyCount, int $viableCount): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table}
                 SET run_count = run_count + 1,
                     property_count = %d,
                     viable_count = %d,
                     last_run_date = %s,
                     updated_at = %s
                 WHERE id = %d",
                $propertyCount,
                $viableCount,
                current_time('mysql'),
                current_time('mysql'),
                $id
            )
        );

        return $result !== false;
    }
}

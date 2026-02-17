<?php

declare(strict_types=1);

namespace BMN\CMA\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_cma_reports table.
 */
class CmaReportRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_cma_reports';
    }

    /**
     * JSON-encode structured fields before creating.
     */
    public function create(array $data): int|false
    {
        $jsonFields = ['subject_data', 'subject_overrides', 'cma_filters', 'summary_statistics'];

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
     * Find reports by subject listing ID.
     *
     * @return object[]
     */
    public function findByListing(string $listingId, int $limit = 10): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE subject_listing_id = %s ORDER BY updated_at DESC LIMIT %d",
                $listingId,
                $limit
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
}

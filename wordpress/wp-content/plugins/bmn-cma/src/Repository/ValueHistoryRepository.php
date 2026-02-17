<?php

declare(strict_types=1);

namespace BMN\CMA\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_cma_value_history table.
 *
 * This table has no updated_at column -- records are append-only.
 */
class ValueHistoryRepository extends Repository
{
    /** @var bool Disable automatic updated_at timestamp. */
    protected bool $timestamps = false;

    protected function getTableName(): string
    {
        return 'bmn_cma_value_history';
    }

    /**
     * Set created_at using current_time('mysql') before creating.
     */
    public function create(array $data): int|false
    {
        $data['created_at'] = current_time('mysql');

        return parent::create($data);
    }

    /**
     * Find value history entries for a listing, most recent first.
     *
     * @return object[]
     */
    public function findByListing(string $listingId, int $limit = 50): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_id = %s ORDER BY created_at DESC LIMIT %d",
                $listingId,
                $limit
            )
        ) ?? [];
    }

    /**
     * Find value history entries for a user, most recent first.
     *
     * @return object[]
     */
    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $userId,
                $limit
            )
        ) ?? [];
    }

    /**
     * Get chronological trend data for a listing (oldest first, for charting).
     *
     * @return object[]
     */
    public function getTrends(string $listingId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_id = %s ORDER BY created_at ASC",
                $listingId
            )
        ) ?? [];
    }
}

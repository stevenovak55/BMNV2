<?php

declare(strict_types=1);

namespace BMN\Exclusive\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_exclusive_listings table.
 */
class ExclusiveListingRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_exclusive_listings';
    }

    /**
     * Get paginated listings for an agent, ordered by updated_at DESC.
     *
     * @param int         $agentUserId The agent's user ID.
     * @param int         $limit       Maximum rows to return.
     * @param int         $offset      Rows to skip.
     * @param string|null $status      Optional status filter.
     *
     * @return object[] Array of listing objects.
     */
    public function findByAgent(int $agentUserId, int $limit = 20, int $offset = 0, ?string $status = null): array
    {
        if ($status !== null) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE agent_user_id = %d AND status = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                    $agentUserId,
                    $status,
                    $limit,
                    $offset
                )
            ) ?? [];
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE agent_user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $agentUserId,
                $limit,
                $offset
            )
        ) ?? [];
    }

    /**
     * Count listings for an agent, optionally filtered by status.
     */
    public function countByAgent(int $agentUserId, ?string $status = null): int
    {
        if ($status !== null) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE agent_user_id = %d AND status = %s",
                    $agentUserId,
                    $status
                )
            );
        }

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE agent_user_id = %d",
                $agentUserId
            )
        );
    }

    /**
     * Find a listing by its unique listing_id (not the auto-increment id).
     */
    public function findByListingId(int $listingId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_id = %d LIMIT 1",
                $listingId
            )
        );

        return $result ?: null;
    }

    /**
     * Get the next available listing_id.
     */
    public function getNextListingId(): int
    {
        return (int) $this->wpdb->get_var(
            "SELECT COALESCE(MAX(listing_id), 0) + 1 FROM {$this->table}"
        );
    }

    /**
     * Update photo_count and main_photo_url for a listing.
     */
    public function updatePhotoInfo(int $id, int $photoCount, ?string $mainPhotoUrl): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET photo_count = %d, main_photo_url = %s, updated_at = %s WHERE id = %d",
                $photoCount,
                $mainPhotoUrl ?? '',
                current_time('mysql'),
                $id
            )
        );

        return $result !== false;
    }

    /**
     * Mark a listing as synced to the properties table.
     */
    public function markSynced(int $id): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET synced_to_properties = 1, updated_at = %s WHERE id = %d",
                current_time('mysql'),
                $id
            )
        );

        return $result !== false;
    }
}

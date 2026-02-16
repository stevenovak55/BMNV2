<?php

declare(strict_types=1);

namespace BMN\Users\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for user favorites (bmn_user_favorites table).
 */
class FavoriteRepository extends Repository
{
    protected bool $timestamps = false;

    protected function getTableName(): string
    {
        return 'bmn_user_favorites';
    }

    /**
     * @return object[]
     */
    public function findByUser(int $userId, int $limit, int $offset): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $userId,
            $limit,
            $offset
        );

        return $this->wpdb->get_results($sql) ?? [];
    }

    public function countByUser(int $userId): int
    {
        return $this->count(['user_id' => $userId]);
    }

    public function findByUserAndListing(int $userId, string $listingId): ?object
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d AND listing_id = %s LIMIT 1",
            $userId,
            $listingId
        );

        $result = $this->wpdb->get_row($sql);

        return $result ?: null;
    }

    /**
     * @return int|false Inserted row ID, or false on failure.
     */
    public function addFavorite(int $userId, string $listingId): int|false
    {
        $result = $this->wpdb->insert($this->table, [
            'user_id'    => $userId,
            'listing_id' => $listingId,
            'created_at' => current_time('mysql'),
        ]);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    public function removeFavorite(int $userId, string $listingId): bool
    {
        $result = $this->wpdb->delete($this->table, [
            'user_id'    => $userId,
            'listing_id' => $listingId,
        ]);

        return $result !== false;
    }

    public function removeAllForUser(int $userId): int
    {
        $result = $this->wpdb->delete($this->table, ['user_id' => $userId]);

        return $result !== false ? (int) $result : 0;
    }

    /**
     * @return string[] Array of listing IDs.
     */
    public function getListingIdsForUser(int $userId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT listing_id FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC",
            $userId
        );

        return $this->wpdb->get_col($sql);
    }
}

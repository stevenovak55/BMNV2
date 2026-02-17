<?php

declare(strict_types=1);

namespace BMN\Exclusive\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_exclusive_photos table.
 */
class ExclusivePhotoRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_exclusive_photos';
    }

    /**
     * Get all photos for a listing ordered by sort_order ASC.
     *
     * @return object[] Array of photo objects.
     */
    public function findByListing(int $exclusiveListingId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE exclusive_listing_id = %d ORDER BY sort_order ASC",
                $exclusiveListingId
            )
        ) ?? [];
    }

    /**
     * Count photos for a listing.
     */
    public function countByListing(int $exclusiveListingId): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE exclusive_listing_id = %d",
                $exclusiveListingId
            )
        );
    }

    /**
     * Delete all photos for a listing (cascade support).
     */
    public function deleteByListing(int $exclusiveListingId): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE exclusive_listing_id = %d",
                $exclusiveListingId
            )
        );

        return $result !== false;
    }

    /**
     * Update sort orders for multiple photos.
     *
     * @param array<int, array{id: int, sort_order: int}> $photoOrders Array of ['id' => int, 'sort_order' => int].
     *
     * @return bool True if all updates succeeded.
     */
    public function updateSortOrders(array $photoOrders): bool
    {
        foreach ($photoOrders as $photoOrder) {
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->table} SET sort_order = %d, updated_at = %s WHERE id = %d",
                    $photoOrder['sort_order'],
                    current_time('mysql'),
                    $photoOrder['id']
                )
            );

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set a photo as the primary photo for a listing.
     *
     * Clears is_primary on all photos for the listing, then sets the given photo.
     */
    public function setPrimary(int $exclusiveListingId, int $photoId): bool
    {
        // Clear all primary flags for this listing.
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET is_primary = 0, updated_at = %s WHERE exclusive_listing_id = %d",
                current_time('mysql'),
                $exclusiveListingId
            )
        );

        if ($result === false) {
            return false;
        }

        // Set the specified photo as primary.
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET is_primary = 1, updated_at = %s WHERE id = %d",
                current_time('mysql'),
                $photoId
            )
        );

        return $result !== false;
    }
}

<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class MediaRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_media';
    }

    /**
     * Replace all media for a listing (delete + batch insert).
     */
    public function replaceForListing(string $listingKey, array $mediaRows): int
    {
        // Delete existing
        $this->wpdb->delete($this->table, ['listing_key' => $listingKey]);

        if (empty($mediaRows)) {
            return 0;
        }

        // Batch insert
        $inserted = 0;
        foreach ($mediaRows as $row) {
            $row['listing_key'] = $listingKey;
            if ($this->create($row) !== false) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Get all media for a listing, ordered by order_index.
     */
    public function getForListing(string $listingKey): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_key = %s ORDER BY order_index ASC",
                $listingKey
            )
        ) ?? [];
    }

    /**
     * Delete all media for a listing.
     */
    public function deleteForListing(string $listingKey): int
    {
        return (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE listing_key = %s",
                $listingKey
            )
        );
    }
}

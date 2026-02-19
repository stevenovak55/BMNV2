<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class RoomRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_rooms';
    }

    /**
     * Replace all rooms for a listing (delete + batch insert).
     */
    public function replaceForListing(string $listingKey, array $roomRows): int
    {
        $this->wpdb->delete($this->table, ['listing_key' => $listingKey]);

        if (empty($roomRows)) {
            return 0;
        }

        $inserted = 0;
        foreach ($roomRows as $row) {
            $row['listing_key'] = $listingKey;
            if ($this->create($row) !== false) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Get all rooms for a listing, ordered by room_type.
     */
    public function getForListing(string $listingKey): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_key = %s ORDER BY room_type ASC",
                $listingKey
            )
        ) ?? [];
    }

    /**
     * Delete all rooms for a listing.
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

<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class OpenHouseRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_open_houses';
    }

    /**
     * Get upcoming open houses for a listing.
     */
    public function getForListing(string $listingKey): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_key = %s AND open_house_date >= CURDATE() ORDER BY open_house_date ASC, open_house_start_time ASC",
                $listingKey
            )
        ) ?? [];
    }

    /**
     * Replace all open houses for a listing.
     */
    public function replaceForListing(string $listingKey, array $rows): int
    {
        $this->wpdb->delete($this->table, ['listing_key' => $listingKey]);

        $inserted = 0;
        foreach ($rows as $row) {
            $row['listing_key'] = $listingKey;
            if ($this->create($row) !== false) {
                $inserted++;
            }
        }
        return $inserted;
    }

    /**
     * Clean up expired open houses older than given days.
     */
    public function cleanupExpired(int $daysOld = 7): int
    {
        return (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE open_house_date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
                $daysOld
            )
        );
    }
}

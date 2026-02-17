<?php

declare(strict_types=1);

namespace BMN\Flip\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_flip_monitor_seen table.
 */
class MonitorSeenRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_flip_monitor_seen';
    }

    /**
     * Mark a listing as seen for a report. Inserts if new, updates score if existing.
     */
    public function markSeen(int $reportId, string $listingId, float $score): bool
    {
        $now = current_time('mysql');

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table} (report_id, listing_id, first_seen_date, last_score, created_at, updated_at)
                 VALUES (%d, %s, %s, %f, %s, %s)
                 ON DUPLICATE KEY UPDATE last_score = %f, updated_at = %s",
                $reportId,
                $listingId,
                $now,
                $score,
                $now,
                $now,
                $score,
                $now
            )
        );

        return $result !== false;
    }

    /**
     * Check if a listing has already been seen for a report.
     */
    public function isNewListing(int $reportId, string $listingId): bool
    {
        $count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE report_id = %d AND listing_id = %s",
                $reportId,
                $listingId
            )
        );

        return $count === 0;
    }

    /**
     * Get all seen listing IDs for a report.
     *
     * @return string[]
     */
    public function getSeenListings(int $reportId): array
    {
        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT listing_id FROM {$this->table} WHERE report_id = %d",
                $reportId
            )
        );

        return $results ?? [];
    }

    /**
     * Delete all seen records for a report.
     */
    public function deleteByReport(int $reportId): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE report_id = %d",
                $reportId
            )
        );

        return $result !== false;
    }
}

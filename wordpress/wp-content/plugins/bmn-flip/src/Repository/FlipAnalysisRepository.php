<?php

declare(strict_types=1);

namespace BMN\Flip\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_flip_analyses table.
 */
class FlipAnalysisRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_flip_analyses';
    }

    /**
     * JSON-encode structured fields before creating.
     */
    public function create(array $data): int|false
    {
        $jsonFields = ['rental_analysis', 'remarks_signals', 'applied_thresholds'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        return parent::create($data);
    }

    /**
     * Get analyses for a report, ordered by total_score DESC.
     *
     * @return object[]
     */
    public function findByReport(int $reportId, int $limit = 100, int $offset = 0): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE report_id = %d ORDER BY total_score DESC LIMIT %d OFFSET %d",
                $reportId,
                $limit,
                $offset
            )
        ) ?? [];
    }

    /**
     * Count total analyses for a report.
     */
    public function countByReport(int $reportId): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE report_id = %d",
                $reportId
            )
        );
    }

    /**
     * Get analyses for a listing, ordered by run_date DESC.
     *
     * @return object[]
     */
    public function findByListing(string $listingId, int $limit = 10): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_id = %s ORDER BY run_date DESC LIMIT %d",
                $listingId,
                $limit
            )
        ) ?? [];
    }

    /**
     * Get viable (non-disqualified) analyses for a report, ordered by total_score DESC.
     *
     * @return object[]
     */
    public function findViable(int $reportId, int $limit = 50, int $offset = 0): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE report_id = %d AND disqualified = 0 ORDER BY total_score DESC LIMIT %d OFFSET %d",
                $reportId,
                $limit,
                $offset
            )
        ) ?? [];
    }

    /**
     * Get a summary of analyses grouped by city for a report.
     *
     * @return object[]|null
     */
    public function getReportSummary(int $reportId): ?array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT city,
                        COUNT(*) as total,
                        SUM(CASE WHEN disqualified = 0 THEN 1 ELSE 0 END) as viable,
                        AVG(total_score) as avg_score,
                        AVG(CASE WHEN disqualified = 0 THEN cash_roi ELSE NULL END) as avg_roi
                 FROM {$this->table}
                 WHERE report_id = %d
                 GROUP BY city",
                $reportId
            )
        );

        return $results ?: null;
    }

    /**
     * Delete all analyses for a report.
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

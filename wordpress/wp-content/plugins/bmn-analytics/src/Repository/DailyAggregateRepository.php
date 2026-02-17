<?php

declare(strict_types=1);

namespace BMN\Analytics\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for the bmn_analytics_daily table.
 *
 * Manages pre-aggregated daily metrics with upsert support via the
 * UNIQUE(aggregate_date, metric_type, dimension) composite key.
 */
class DailyAggregateRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_analytics_daily';
    }

    /**
     * Insert or update a daily aggregate record.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE to atomically upsert
     * on the (aggregate_date, metric_type, dimension) unique key.
     *
     * @param array<string, mixed> $data Must include aggregate_date, metric_type, metric_value.
     */
    public function upsert(array $data): bool
    {
        $now = current_time('mysql');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $now;

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($data as $column => $value) {
            $sanitizedColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            $columns[] = "`{$sanitizedColumn}`";

            if (is_int($value)) {
                $placeholders[] = '%d';
            } elseif (is_float($value)) {
                $placeholders[] = '%f';
            } elseif ($value === null) {
                $placeholders[] = '%s';
            } else {
                $placeholders[] = '%s';
            }

            $values[] = $value;
        }

        $columnList = implode(', ', $columns);
        $placeholderList = implode(', ', $placeholders);

        $sql = "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholderList})
                ON DUPLICATE KEY UPDATE
                    `metric_value` = VALUES(`metric_value`),
                    `metadata` = VALUES(`metadata`),
                    `updated_at` = VALUES(`updated_at`)";

        $prepared = $this->wpdb->prepare($sql, ...$values);

        return $this->wpdb->query($prepared) !== false;
    }

    /**
     * Get daily aggregate records for a metric type within a date range.
     *
     * @param string      $metricType The metric to query (e.g. 'pageviews').
     * @param string      $startDate  Start date (inclusive, Y-m-d).
     * @param string      $endDate    End date (exclusive, Y-m-d).
     * @param string|null $dimension  Optional dimension filter.
     *
     * @return object[] Rows ordered by aggregate_date ASC.
     */
    public function getByDateRange(string $metricType, string $startDate, string $endDate, ?string $dimension = null): array
    {
        if ($dimension !== null) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE metric_type = %s
                       AND aggregate_date >= %s
                       AND aggregate_date < %s
                       AND dimension = %s
                     ORDER BY aggregate_date ASC",
                    $metricType,
                    $startDate,
                    $endDate,
                    $dimension
                )
            );
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE metric_type = %s
                   AND aggregate_date >= %s
                   AND aggregate_date < %s
                 ORDER BY aggregate_date ASC",
                $metricType,
                $startDate,
                $endDate
            )
        );
    }

    /**
     * Get summed totals for each metric type within a date range.
     *
     * @return object[] Each row has metric_type and total_value.
     */
    public function getTotals(string $startDate, string $endDate): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT metric_type, SUM(metric_value) AS total_value
                 FROM {$this->table}
                 WHERE aggregate_date >= %s
                   AND aggregate_date < %s
                   AND dimension IS NULL
                 GROUP BY metric_type",
                $startDate,
                $endDate
            )
        );
    }

    /**
     * Get top dimensions for a metric type within a date range.
     *
     * @return object[] Each row has dimension and total_value, ordered by total_value DESC.
     */
    public function getTopDimensions(string $metricType, string $startDate, string $endDate, int $limit = 10): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT dimension, SUM(metric_value) AS total_value
                 FROM {$this->table}
                 WHERE metric_type = %s
                   AND aggregate_date >= %s
                   AND aggregate_date < %s
                   AND dimension IS NOT NULL
                 GROUP BY dimension
                 ORDER BY total_value DESC
                 LIMIT %d",
                $metricType,
                $startDate,
                $endDate,
                $limit
            )
        );
    }
}

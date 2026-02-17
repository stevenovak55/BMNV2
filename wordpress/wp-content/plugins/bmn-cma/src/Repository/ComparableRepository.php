<?php

declare(strict_types=1);

namespace BMN\CMA\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_comparables table.
 */
class ComparableRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_comparables';
    }

    /**
     * JSON-encode structured fields before creating.
     */
    public function create(array $data): int|false
    {
        $jsonFields = ['adjustments', 'property_data'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        return parent::create($data);
    }

    /**
     * Find all comparables for a report, ordered by best comparability score.
     *
     * @return object[]
     */
    public function findByReport(int $reportId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE report_id = %d ORDER BY comparability_score DESC",
                $reportId
            )
        ) ?? [];
    }

    /**
     * Delete all comparables for a report.
     *
     * @return int Number of rows deleted.
     */
    public function deleteByReport(int $reportId): int
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE report_id = %d",
                $reportId
            )
        );

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Insert or update a comparable using the report_listing unique key.
     */
    public function upsert(array $data): bool
    {
        $jsonFields = ['adjustments', 'property_data'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        $now = current_time('mysql');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $now;

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(function (string $key) use ($data): string {
            $value = $data[$key];
            if ($value === null) {
                return 'NULL';
            }
            if (is_int($value)) {
                return '%d';
            }
            if (is_float($value)) {
                return '%f';
            }
            return '%s';
        }, array_keys($data)));

        $updateParts = [];
        $updateFields = [
            'close_price', 'adjusted_price', 'adjustment_total', 'adjustments',
            'comparability_score', 'comparability_grade', 'distance_miles',
            'property_data', 'is_selected', 'updated_at',
        ];

        foreach ($updateFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateParts[] = "{$field} = VALUES({$field})";
            }
        }

        $updateClause = implode(', ', $updateParts);
        $values = array_filter(array_values($data), static fn (mixed $v): bool => $v !== null);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE {$updateClause}";

        $result = $this->wpdb->query(
            $this->wpdb->prepare($sql, ...$values)
        );

        return $result !== false;
    }
}

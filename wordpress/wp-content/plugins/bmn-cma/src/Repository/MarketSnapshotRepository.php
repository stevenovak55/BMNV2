<?php

declare(strict_types=1);

namespace BMN\CMA\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_market_snapshots table.
 */
class MarketSnapshotRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_market_snapshots';
    }

    /**
     * Get the most recent snapshot for a city and property type.
     */
    public function getLatest(string $city, string $propertyType = 'all'): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE city = %s AND property_type = %s
                 ORDER BY snapshot_date DESC
                 LIMIT 1",
                $city,
                $propertyType
            )
        );

        return $result ?: null;
    }

    /**
     * Get snapshots within a date range for a city and property type.
     *
     * @return object[]
     */
    public function getRange(string $city, string $propertyType, string $startDate, string $endDate): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE city = %s AND property_type = %s
                   AND snapshot_date BETWEEN %s AND %s
                 ORDER BY snapshot_date ASC",
                $city,
                $propertyType,
                $startDate,
                $endDate
            )
        ) ?? [];
    }

    /**
     * Insert or update a snapshot using the city_type_date unique key.
     */
    public function upsert(array $data): bool
    {
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
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
            'active_listings', 'new_listings', 'closed_sales',
            'median_price', 'avg_price', 'median_dom', 'avg_dom',
            'median_price_per_sqft', 'months_supply', 'list_to_sale_ratio',
            'metadata', 'updated_at',
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

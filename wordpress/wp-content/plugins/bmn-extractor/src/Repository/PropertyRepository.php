<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class PropertyRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_properties';
    }

    /**
     * Find a property by its listing_key (Bridge API hash).
     */
    public function findByListingKey(string $listingKey): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_key = %s LIMIT 1",
                $listingKey
            )
        );
        return $result ?: null;
    }

    /**
     * Find a property by its listing_id (MLS number for URLs).
     */
    public function findByListingId(string $listingId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_id = %s LIMIT 1",
                $listingId
            )
        );
        return $result ?: null;
    }

    /**
     * Upsert a single property using INSERT ... ON DUPLICATE KEY UPDATE.
     * Returns 'created' or 'updated'.
     */
    public function upsert(array $data): string
    {
        // Build the INSERT ... ON DUPLICATE KEY UPDATE query
        // Key: listing_key (unique)
        $now = current_time('mysql');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $now;

        $columns = array_keys($data);
        $sanitizedColumns = array_map(fn($c) => '`' . preg_replace('/[^a-zA-Z0-9_]/', '', $c) . '`', $columns);
        $placeholders = [];
        $values = [];
        $updateParts = [];

        foreach ($columns as $col) {
            $val = $data[$col];
            $placeholder = is_int($val) ? '%d' : (is_float($val) ? '%f' : '%s');
            $placeholders[] = $placeholder;
            $values[] = $val;

            if ($col !== 'listing_key' && $col !== 'created_at') {
                $sanitized = '`' . preg_replace('/[^a-zA-Z0-9_]/', '', $col) . '`';
                $updateParts[] = "{$sanitized} = VALUES({$sanitized})";
            }
        }

        $columnList = implode(', ', $sanitizedColumns);
        $placeholderList = implode(', ', $placeholders);
        $updateList = implode(', ', $updateParts);

        $sql = "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholderList}) ON DUPLICATE KEY UPDATE {$updateList}";
        $prepared = $this->wpdb->prepare($sql, ...$values);
        $result = $this->wpdb->query($prepared);

        // affected_rows: 1 = inserted, 2 = updated
        return ($result === 1) ? 'created' : 'updated';
    }

    /**
     * Batch upsert properties in chunks of 100.
     * Returns ['created' => int, 'updated' => int].
     */
    public function batchUpsert(array $rows): array
    {
        $stats = ['created' => 0, 'updated' => 0];

        foreach (array_chunk($rows, 100) as $chunk) {
            foreach ($chunk as $row) {
                $result = $this->upsert($row);
                $stats[$result]++;
            }
        }

        return $stats;
    }

    /**
     * Count properties by standard_status.
     * Returns ['Active' => N, 'Closed' => N, ...].
     */
    public function countByStatus(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT standard_status, COUNT(*) as cnt FROM {$this->table} GROUP BY standard_status"
        );

        $counts = [];
        foreach ($results ?? [] as $row) {
            $counts[$row->standard_status] = (int) $row->cnt;
        }
        return $counts;
    }

    /**
     * Get the latest modification_timestamp from the table.
     */
    public function getLastModificationTimestamp(): ?string
    {
        return $this->wpdb->get_var(
            "SELECT MAX(modification_timestamp) FROM {$this->table}"
        );
    }

    /**
     * Mark listings not seen in this extraction as archived.
     * @param array $seenListingKeys All listing_keys seen in this run
     */
    public function archiveStaleListings(array $seenListingKeys): int
    {
        if (empty($seenListingKeys)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($seenListingKeys), '%s'));
        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table} SET is_archived = 1, updated_at = %s WHERE listing_key NOT IN ({$placeholders}) AND is_archived = 0",
            current_time('mysql'),
            ...$seenListingKeys
        );

        return (int) $this->wpdb->query($sql);
    }
}

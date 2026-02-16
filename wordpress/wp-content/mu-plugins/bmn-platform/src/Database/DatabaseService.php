<?php

declare(strict_types=1);

namespace BMN\Platform\Database;

use wpdb;
use RuntimeException;

/**
 * Central database service for the BMN Platform.
 *
 * Wraps the WordPress $wpdb instance and provides utility methods for
 * table management, health checks, batch operations, and query building.
 *
 * All dynamic SQL values are passed through $wpdb->prepare(). Timestamps
 * use current_time('mysql') to respect the WordPress timezone setting.
 *
 * Usage:
 *
 *     $db = new DatabaseService($wpdb);
 *     $table = $db->getTable('bmn_properties');  // "wp_bmn_properties"
 *     $db->setTimezone('America/New_York');
 *
 *     $results = $db->getQueryBuilder()
 *         ->table($table)
 *         ->where('city', '=', 'Boston')
 *         ->get();
 */
final class DatabaseService
{
    private readonly wpdb $wpdb;

    // ------------------------------------------------------------------
    // Constructor
    // ------------------------------------------------------------------

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Return the underlying wpdb instance.
     */
    public function getWpdb(): wpdb
    {
        return $this->wpdb;
    }

    /**
     * Return a fully prefixed table name.
     *
     * Example: getTable('bmn_properties') => 'wp_bmn_properties'
     *
     * @param string $name Unprefixed table name.
     */
    public function getTable(string $name): string
    {
        return $this->wpdb->prefix . $name;
    }

    /**
     * Set the MySQL session timezone.
     *
     * Defaults to 'America/New_York' to match the BMN Boston locale.
     *
     * @param string $timezone A valid MySQL timezone identifier.
     */
    public function setTimezone(string $timezone = 'America/New_York'): void
    {
        $this->wpdb->query(
            $this->wpdb->prepare('SET time_zone = %s', $timezone)
        );
    }

    /**
     * Perform a database health check.
     *
     * Returns connection status, table prefix, and charset information.
     *
     * @return array{connected: bool, prefix: string, charset: string}
     */
    public function healthCheck(): array
    {
        $connected = false;

        try {
            $result = $this->wpdb->get_var('SELECT 1');
            $connected = ($result === '1' || $result === 1);
        } catch (\Throwable $e) {
            // Connection failed -- $connected remains false.
        }

        return [
            'connected' => $connected,
            'prefix'    => $this->wpdb->prefix,
            'charset'   => $this->wpdb->charset,
        ];
    }

    /**
     * Check whether a table exists in the database.
     *
     * @param string $tableName Fully-qualified table name (including prefix).
     */
    public function tableExists(string $tableName): bool
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $tableName)
        );

        return $result !== null;
    }

    /**
     * Insert multiple rows efficiently using batched INSERT statements.
     *
     * Rows are inserted in chunks of 500 to avoid exceeding the MySQL
     * max_allowed_packet size.
     *
     * @param string   $table  Fully-qualified table name.
     * @param array[]  $rows   Array of associative arrays (column => value).
     * @param string[] $format Optional wpdb format hints for each column
     *                         (e.g. ['%s', '%d']). If empty, format is inferred.
     *
     * @return int Number of rows inserted.
     *
     * @throws RuntimeException If the INSERT query fails.
     */
    public function batchInsert(string $table, array $rows, array $format = []): int
    {
        if ($rows === []) {
            return 0;
        }

        $chunkSize = 500;
        $totalInserted = 0;
        $columns = array_keys($rows[0]);

        // Sanitize column names.
        $sanitizedColumns = array_map(
            fn (string $col): string => preg_replace('/[^a-zA-Z0-9_]/', '', $col),
            $columns
        );

        $columnList = '`' . implode('`, `', $sanitizedColumns) . '`';

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $valueTuples = [];
            $prepareValues = [];

            foreach ($chunk as $row) {
                $placeholders = [];

                foreach ($columns as $index => $column) {
                    $value = $row[$column] ?? null;

                    if ($format !== [] && isset($format[$index])) {
                        $placeholders[] = $format[$index];
                    } elseif (is_int($value)) {
                        $placeholders[] = '%d';
                    } elseif (is_float($value)) {
                        $placeholders[] = '%f';
                    } else {
                        $placeholders[] = '%s';
                    }

                    $prepareValues[] = $value;
                }

                $valueTuples[] = '(' . implode(', ', $placeholders) . ')';
            }

            $sql = "INSERT INTO {$table} ({$columnList}) VALUES " . implode(', ', $valueTuples);
            $prepared = $this->wpdb->prepare($sql, ...$prepareValues);

            $result = $this->wpdb->query($prepared);

            if ($result === false) {
                throw new RuntimeException(
                    "Batch insert into {$table} failed: {$this->wpdb->last_error}"
                );
            }

            $totalInserted += (int) $result;
        }

        return $totalInserted;
    }

    /**
     * Perform multiple row updates using CASE/WHEN SQL.
     *
     * Each element in $updates must contain the key column value plus any
     * columns to update. Example:
     *
     *     $updates = [
     *         ['id' => 1, 'status' => 'active', 'price' => 500000],
     *         ['id' => 2, 'status' => 'sold',   'price' => 450000],
     *     ];
     *     $db->batchUpdate('wp_bmn_properties', $updates, 'id');
     *
     * Generates:
     *     UPDATE wp_bmn_properties
     *     SET status = CASE id WHEN 1 THEN 'active' WHEN 2 THEN 'sold' END,
     *         price  = CASE id WHEN 1 THEN 500000   WHEN 2 THEN 450000 END
     *     WHERE id IN (1, 2)
     *
     * @param string $table     Fully-qualified table name.
     * @param array  $updates   Array of associative arrays with key + columns.
     * @param string $keyColumn The column used to match rows (default: 'id').
     *
     * @return int Total rows affected.
     *
     * @throws RuntimeException If the UPDATE query fails.
     */
    public function batchUpdate(string $table, array $updates, string $keyColumn = 'id'): int
    {
        if ($updates === []) {
            return 0;
        }

        $keyColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $keyColumn);

        // Determine all columns to update (everything except the key column).
        $updateColumns = [];
        foreach ($updates as $row) {
            foreach (array_keys($row) as $col) {
                if ($col !== $keyColumn && ! in_array($col, $updateColumns, true)) {
                    $updateColumns[] = $col;
                }
            }
        }

        if ($updateColumns === []) {
            return 0;
        }

        // Build CASE/WHEN for each column.
        $setClauses = [];
        $prepareValues = [];

        foreach ($updateColumns as $column) {
            $sanitizedColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            $cases = [];

            foreach ($updates as $row) {
                if (! array_key_exists($column, $row)) {
                    continue;
                }

                $keyValue = $row[$keyColumn];
                $newValue = $row[$column];

                $keyPlaceholder = is_int($keyValue) ? '%d' : '%s';
                $valPlaceholder = is_int($newValue) ? '%d' : (is_float($newValue) ? '%f' : '%s');

                $cases[] = "WHEN {$keyPlaceholder} THEN {$valPlaceholder}";
                $prepareValues[] = $keyValue;
                $prepareValues[] = $newValue;
            }

            if ($cases !== []) {
                $setClauses[] = "`{$sanitizedColumn}` = CASE `{$keyColumn}` " . implode(' ', $cases) . ' END';
            }
        }

        // Build the WHERE IN clause.
        $keyValues = array_column($updates, $keyColumn);
        $inPlaceholders = [];

        foreach ($keyValues as $kv) {
            $inPlaceholders[] = is_int($kv) ? '%d' : '%s';
            $prepareValues[] = $kv;
        }

        $sql = "UPDATE {$table} SET "
            . implode(', ', $setClauses)
            . " WHERE `{$keyColumn}` IN (" . implode(', ', $inPlaceholders) . ')';

        $prepared = $this->wpdb->prepare($sql, ...$prepareValues);
        $result = $this->wpdb->query($prepared);

        if ($result === false) {
            throw new RuntimeException(
                "Batch update on {$table} failed: {$this->wpdb->last_error}"
            );
        }

        return (int) $result;
    }

    /**
     * Return a new QueryBuilder instance.
     *
     * The builder provides a fluent interface for constructing SELECT queries.
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->wpdb);
    }
}

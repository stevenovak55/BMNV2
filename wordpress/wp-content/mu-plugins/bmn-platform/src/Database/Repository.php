<?php

declare(strict_types=1);

namespace BMN\Platform\Database;

use wpdb;

/**
 * Abstract base repository with common CRUD operations.
 *
 * All queries use $wpdb->prepare() for safety. Timestamps use
 * current_time('mysql') to respect the WordPress timezone setting.
 *
 * Subclasses must implement getTableName() to return the unprefixed table
 * name. The repository automatically prepends $wpdb->prefix.
 */
abstract class Repository
{
    protected readonly wpdb $wpdb;

    /** Fully-qualified table name including prefix. */
    protected readonly string $table;

    /** Primary key column name. Override if your table uses something other than "id". */
    protected string $primaryKey = 'id';

    /** Whether the table has created_at / updated_at columns. */
    protected bool $timestamps = true;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . $this->getTableName();
    }

    // ------------------------------------------------------------------
    // Abstract
    // ------------------------------------------------------------------

    /**
     * Return the unprefixed table name (e.g. "bmn_properties").
     */
    abstract protected function getTableName(): string;

    // ------------------------------------------------------------------
    // CRUD operations
    // ------------------------------------------------------------------

    /**
     * Find a single record by primary key.
     *
     * @return object|null The row as a stdClass object, or null if not found.
     */
    public function find(int|string $id): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = %s LIMIT 1",
                $id
            )
        );

        return $result ?: null;
    }

    /**
     * Find records matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column => value pairs (AND logic).
     * @param int                  $limit    Maximum rows to return (0 = no limit).
     * @param int                  $offset   Rows to skip.
     * @param string               $orderBy  Column to order by.
     * @param string               $order    ASC or DESC.
     *
     * @return object[] Array of stdClass objects.
     */
    public function findBy(
        array $criteria,
        int $limit = 0,
        int $offset = 0,
        string $orderBy = 'id',
        string $order = 'ASC',
    ): array {
        $where = [];
        $values = [];

        foreach ($criteria as $column => $value) {
            $placeholder = is_int($value) ? '%d' : '%s';
            $where[] = "{$column} = {$placeholder}";
            $values[] = $value;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        // Sanitize order direction.
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        // Sanitize orderBy to prevent injection (allow only alphanumeric + underscore).
        $orderBy = preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy);

        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY {$orderBy} {$order}";

        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = $limit;
            $values[] = $offset;
        }

        if ($values !== []) {
            $sql = $this->wpdb->prepare($sql, ...$values);
        }

        return $this->wpdb->get_results($sql);
    }

    /**
     * Insert a new record.
     *
     * @param array<string, mixed> $data Column => value pairs.
     *
     * @return int|false The inserted row ID, or false on failure.
     */
    public function create(array $data): int|false
    {
        if ($this->timestamps) {
            $now = current_time('mysql');
            $data['created_at'] = $data['created_at'] ?? $now;
            $data['updated_at'] = $data['updated_at'] ?? $now;
        }

        $result = $this->wpdb->insert($this->table, $data);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Update an existing record by primary key.
     *
     * @param int|string           $id   The primary-key value.
     * @param array<string, mixed> $data Column => value pairs to update.
     *
     * @return bool True if the row was updated (or no changes needed), false on error.
     */
    public function update(int|string $id, array $data): bool
    {
        if ($this->timestamps) {
            $data['updated_at'] = $data['updated_at'] ?? current_time('mysql');
        }

        $result = $this->wpdb->update(
            $this->table,
            $data,
            [$this->primaryKey => $id]
        );

        return $result !== false;
    }

    /**
     * Delete a record by primary key.
     *
     * @return bool True if the row was deleted, false otherwise.
     */
    public function delete(int|string $id): bool
    {
        $result = $this->wpdb->delete(
            $this->table,
            [$this->primaryKey => $id]
        );

        return $result !== false;
    }

    // ------------------------------------------------------------------
    // Utility helpers
    // ------------------------------------------------------------------

    /**
     * Count records matching optional criteria.
     *
     * @param array<string, mixed> $criteria Column => value pairs (AND logic).
     */
    public function count(array $criteria = []): int
    {
        $where = [];
        $values = [];

        foreach ($criteria as $column => $value) {
            $placeholder = is_int($value) ? '%d' : '%s';
            $where[] = "{$column} = {$placeholder}";
            $values[] = $value;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";

        if ($values !== []) {
            $sql = $this->wpdb->prepare($sql, ...$values);
        }

        return (int) $this->wpdb->get_var($sql);
    }
}

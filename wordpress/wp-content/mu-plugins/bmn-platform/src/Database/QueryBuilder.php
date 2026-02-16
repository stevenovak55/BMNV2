<?php

declare(strict_types=1);

namespace BMN\Platform\Database;

use wpdb;
use InvalidArgumentException;

/**
 * Fluent SQL query builder.
 *
 * Provides a chainable interface for constructing SELECT queries against
 * the WordPress database. All dynamic values are passed through
 * $wpdb->prepare() and all identifiers are sanitized to prevent injection.
 *
 * Usage:
 *
 *     $builder = new QueryBuilder($wpdb);
 *     $results = $builder
 *         ->table('wp_bmn_properties')
 *         ->select('listing_id', 'price', 'city')
 *         ->where('city', '=', 'Boston')
 *         ->where('price', '>', 500000)
 *         ->orderBy('price', 'DESC')
 *         ->limit(25)
 *         ->get();
 */
final class QueryBuilder
{
    private readonly wpdb $wpdb;

    private string $table = '';

    /** @var string[] */
    private array $columns = ['*'];

    /** @var array<int, array{sql: string, values: array}> */
    private array $wheres = [];

    /** @var array<int, array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    /** @var string[] */
    private array $groupByColumns = [];

    /** @var array<int, array{type: string, table: string, first: string, operator: string, second: string}> */
    private array $joins = [];

    // ------------------------------------------------------------------
    // Constructor
    // ------------------------------------------------------------------

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    // ------------------------------------------------------------------
    // Builder methods (return $this for chaining)
    // ------------------------------------------------------------------

    /**
     * Set the FROM table.
     */
    public function table(string $table): self
    {
        $this->table = $this->sanitizeIdentifier($table);

        return $this;
    }

    /**
     * Set the SELECT columns.
     *
     * Pass individual column names as arguments. Defaults to '*' if never called.
     */
    public function select(string ...$columns): self
    {
        $this->columns = array_map(
            fn (string $col): string => $col === '*' ? '*' : $this->sanitizeIdentifier($col),
            $columns
        );

        return $this;
    }

    /**
     * Add a WHERE clause.
     *
     * @param string $column   Column name.
     * @param string $operator Comparison operator (=, !=, <, >, <=, >=, LIKE).
     * @param mixed  $value    The value to compare against.
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $this->validateOperator($operator);
        $column = $this->sanitizeIdentifier($column);
        $placeholder = is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s');

        $this->wheres[] = [
            'sql'    => "{$column} {$operator} {$placeholder}",
            'values' => [$value],
        ];

        return $this;
    }

    /**
     * Add a WHERE IN clause.
     *
     * @param string $column Column name.
     * @param array  $values List of values.
     */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            // Empty IN list matches nothing -- add an always-false condition.
            $this->wheres[] = [
                'sql'    => '1 = 0',
                'values' => [],
            ];

            return $this;
        }

        $column = $this->sanitizeIdentifier($column);
        $placeholders = [];

        foreach ($values as $value) {
            $placeholders[] = is_int($value) ? '%d' : '%s';
        }

        $this->wheres[] = [
            'sql'    => "{$column} IN (" . implode(', ', $placeholders) . ')',
            'values' => array_values($values),
        ];

        return $this;
    }

    /**
     * Add a WHERE BETWEEN clause.
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $column = $this->sanitizeIdentifier($column);
        $minPlaceholder = is_int($min) ? '%d' : (is_float($min) ? '%f' : '%s');
        $maxPlaceholder = is_int($max) ? '%d' : (is_float($max) ? '%f' : '%s');

        $this->wheres[] = [
            'sql'    => "{$column} BETWEEN {$minPlaceholder} AND {$maxPlaceholder}",
            'values' => [$min, $max],
        ];

        return $this;
    }

    /**
     * Add a WHERE IS NULL clause.
     */
    public function whereNull(string $column): self
    {
        $column = $this->sanitizeIdentifier($column);

        $this->wheres[] = [
            'sql'    => "{$column} IS NULL",
            'values' => [],
        ];

        return $this;
    }

    /**
     * Add a WHERE IS NOT NULL clause.
     */
    public function whereNotNull(string $column): self
    {
        $column = $this->sanitizeIdentifier($column);

        $this->wheres[] = [
            'sql'    => "{$column} IS NOT NULL",
            'values' => [],
        ];

        return $this;
    }

    /**
     * Add an ORDER BY clause.
     *
     * @param string $column    Column to order by.
     * @param string $direction ASC or DESC.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column'    => $this->sanitizeIdentifier($column),
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    /**
     * Set the LIMIT.
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;

        return $this;
    }

    /**
     * Set the OFFSET.
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;

        return $this;
    }

    /**
     * Set GROUP BY columns.
     */
    public function groupBy(string ...$columns): self
    {
        $this->groupByColumns = array_map(
            fn (string $col): string => $this->sanitizeIdentifier($col),
            $columns
        );

        return $this;
    }

    /**
     * Add a JOIN clause.
     *
     * @param string $table    Table to join.
     * @param string $first    Left-side column.
     * @param string $operator Comparison operator.
     * @param string $second   Right-side column.
     * @param string $type     Join type (INNER, LEFT, RIGHT, CROSS).
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER',
    ): self {
        $this->validateOperator($operator);

        $allowedTypes = ['INNER', 'LEFT', 'RIGHT', 'CROSS'];
        $type = strtoupper($type);

        if (! in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException("Invalid join type: {$type}");
        }

        $this->joins[] = [
            'type'     => $type,
            'table'    => $this->sanitizeIdentifier($table),
            'first'    => $this->sanitizeIdentifier($first),
            'operator' => $operator,
            'second'   => $this->sanitizeIdentifier($second),
        ];

        return $this;
    }

    // ------------------------------------------------------------------
    // Terminal methods
    // ------------------------------------------------------------------

    /**
     * Execute the query and return all matching rows.
     *
     * @return object[] Array of stdClass objects.
     */
    public function get(): array
    {
        $sql = $this->buildSql();
        $prepared = $this->prepareQuery($sql);

        return $this->wpdb->get_results($prepared);
    }

    /**
     * Execute the query and return the first matching row.
     */
    public function first(): ?object
    {
        $this->limitValue = 1;
        $sql = $this->buildSql();
        $prepared = $this->prepareQuery($sql);

        $result = $this->wpdb->get_row($prepared);

        return $result ?: null;
    }

    /**
     * Execute a COUNT(*) query and return the count.
     */
    public function count(): int
    {
        $savedColumns = $this->columns;
        $this->columns = ['COUNT(*)'];

        $sql = $this->buildSql();
        $prepared = $this->prepareQuery($sql);

        $result = (int) $this->wpdb->get_var($prepared);

        // Restore columns in case the builder is reused.
        $this->columns = $savedColumns;

        return $result;
    }

    /**
     * Return the built SQL string for debugging.
     *
     * Dynamic values are interpolated via $wpdb->prepare().
     */
    public function toSql(): string
    {
        $sql = $this->buildSql();

        return $this->prepareQuery($sql);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Build the raw SQL string with placeholders.
     */
    private function buildSql(): string
    {
        if ($this->table === '') {
            throw new InvalidArgumentException('No table specified. Call table() before executing a query.');
        }

        $parts = [];

        // SELECT
        $parts[] = 'SELECT ' . implode(', ', $this->columns);

        // FROM
        $parts[] = "FROM {$this->table}";

        // JOINs
        foreach ($this->joins as $join) {
            $parts[] = "{$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // WHERE
        if ($this->wheres !== []) {
            $clauses = array_column($this->wheres, 'sql');
            $parts[] = 'WHERE ' . implode(' AND ', $clauses);
        }

        // GROUP BY
        if ($this->groupByColumns !== []) {
            $parts[] = 'GROUP BY ' . implode(', ', $this->groupByColumns);
        }

        // ORDER BY
        if ($this->orders !== []) {
            $orderParts = array_map(
                fn (array $o): string => "{$o['column']} {$o['direction']}",
                $this->orders
            );
            $parts[] = 'ORDER BY ' . implode(', ', $orderParts);
        }

        // LIMIT
        if ($this->limitValue !== null) {
            $parts[] = 'LIMIT %d';
        }

        // OFFSET
        if ($this->offsetValue !== null) {
            $parts[] = 'OFFSET %d';
        }

        return implode(' ', $parts);
    }

    /**
     * Collect all bound values and run $wpdb->prepare() on the built SQL.
     *
     * If there are no dynamic values, the raw SQL is returned as-is.
     */
    private function prepareQuery(string $sql): string
    {
        $values = [];

        foreach ($this->wheres as $where) {
            foreach ($where['values'] as $value) {
                $values[] = $value;
            }
        }

        if ($this->limitValue !== null) {
            $values[] = $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $values[] = $this->offsetValue;
        }

        if ($values === []) {
            return $sql;
        }

        return $this->wpdb->prepare($sql, ...$values);
    }

    /**
     * Sanitize a SQL identifier (table or column name).
     *
     * Only alphanumeric characters, underscores, and dots are allowed.
     * Dots support qualified names like "table.column".
     *
     * @throws InvalidArgumentException If the identifier is empty after sanitization.
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_.]/', '', $identifier);

        if ($sanitized === '' || $sanitized === null) {
            throw new InvalidArgumentException("Invalid SQL identifier: '{$identifier}'");
        }

        return $sanitized;
    }

    /**
     * Validate that an operator is in the allowed set.
     *
     * @throws InvalidArgumentException If the operator is not allowed.
     */
    private function validateOperator(string $operator): void
    {
        $allowed = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', '<>'];

        if (! in_array(strtoupper($operator), $allowed, true)) {
            throw new InvalidArgumentException("Invalid SQL operator: '{$operator}'");
        }
    }
}

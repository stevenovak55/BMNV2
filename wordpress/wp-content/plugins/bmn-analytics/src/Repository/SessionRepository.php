<?php

declare(strict_types=1);

namespace BMN\Analytics\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for the bmn_analytics_sessions table.
 *
 * Manages visitor sessions with upsert support via the UNIQUE session_id key.
 * Timestamps are NOT auto-managed by the parent (custom first_seen_at / last_seen_at).
 */
class SessionRepository extends Repository
{
    protected bool $timestamps = false;

    protected function getTableName(): string
    {
        return 'bmn_analytics_sessions';
    }

    /**
     * Find a session by its session_id string (not the auto-increment primary key).
     */
    public function findBySessionId(string $sessionId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE session_id = %s LIMIT 1",
                $sessionId
            )
        );

        return $result ?: null;
    }

    /**
     * Create a new session or update an existing one (upsert on session_id UNIQUE key).
     *
     * On duplicate key, updates: user_id, last_seen_at, page_views, events_count.
     *
     * @param array<string, mixed> $data Session data including session_id.
     *
     * @return int|false The insert ID on success, or false on failure.
     */
    public function createOrUpdate(array $data): int|false
    {
        $now = current_time('mysql');
        $data['first_seen_at'] = $data['first_seen_at'] ?? $now;
        $data['last_seen_at'] = $data['last_seen_at'] ?? $now;

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
            } else {
                $placeholders[] = '%s';
            }

            $values[] = $value;
        }

        $columnList = implode(', ', $columns);
        $placeholderList = implode(', ', $placeholders);

        // On duplicate key, update mutable fields.
        $updateClauses = [];
        $updateFields = ['user_id', 'last_seen_at', 'page_views', 'events_count'];

        foreach ($updateFields as $field) {
            if (array_key_exists($field, $data)) {
                $sanitizedField = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                $updateClauses[] = "`{$sanitizedField}` = VALUES(`{$sanitizedField}`)";
            }
        }

        $updateClause = $updateClauses !== []
            ? 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updateClauses)
            : '';

        $sql = "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholderList}) {$updateClause}";

        $prepared = $this->wpdb->prepare($sql, ...$values);
        $result = $this->wpdb->query($prepared);

        if ($result === false) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Get sessions that have been active within the last N minutes.
     *
     * @return object[] Active sessions ordered by last_seen_at DESC.
     */
    public function getActiveSessions(int $minutes = 15): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE last_seen_at >= DATE_SUB(%s, INTERVAL %d MINUTE)
                 ORDER BY last_seen_at DESC",
                current_time('mysql'),
                $minutes
            )
        );
    }

    /**
     * Count unique sessions within a date range.
     */
    public function countUnique(string $startDate, string $endDate): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM {$this->table}
                 WHERE first_seen_at >= %s
                   AND first_seen_at < %s",
                $startDate,
                $endDate
            )
        );
    }

    /**
     * Get traffic source breakdown for a date range.
     *
     * @return object[] Each row has traffic_source and session_count.
     */
    public function getTrafficSources(string $startDate, string $endDate): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT traffic_source, COUNT(*) AS session_count
                 FROM {$this->table}
                 WHERE first_seen_at >= %s
                   AND first_seen_at < %s
                 GROUP BY traffic_source
                 ORDER BY session_count DESC",
                $startDate,
                $endDate
            )
        );
    }
}

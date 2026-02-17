<?php

declare(strict_types=1);

namespace BMN\Analytics\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for the bmn_analytics_events table.
 *
 * Handles creation and querying of individual tracking events.
 * Timestamps are NOT auto-managed by the parent (no updated_at column).
 */
class EventRepository extends Repository
{
    protected bool $timestamps = false;

    protected function getTableName(): string
    {
        return 'bmn_analytics_events';
    }

    /**
     * Insert a new event record.
     *
     * Sets created_at automatically and JSON-encodes the metadata field
     * if it is provided as an array.
     *
     * @param array<string, mixed> $data Column => value pairs.
     *
     * @return int|false The inserted row ID, or false on failure.
     */
    public function create(array $data): int|false
    {
        $data['created_at'] = $data['created_at'] ?? current_time('mysql');

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        $result = $this->wpdb->insert($this->table, $data);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Find events for a specific session.
     *
     * @return object[] Events ordered by most recent first.
     */
    public function findBySession(string $sessionId, int $limit = 100): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE session_id = %s ORDER BY created_at DESC LIMIT %d",
                $sessionId,
                $limit
            )
        );
    }

    /**
     * Find events for a specific user.
     *
     * @return object[] Events ordered by most recent first.
     */
    public function findByUser(int $userId, int $limit = 100): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $userId,
                $limit
            )
        );
    }

    /**
     * Count events of a given type within a date range.
     */
    public function countByType(string $eventType, string $startDate, string $endDate): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE event_type = %s
                   AND created_at >= %s
                   AND created_at < %s",
                $eventType,
                $startDate,
                $endDate
            )
        );
    }

    /**
     * Get the most popular entities for a given event type within a date range.
     *
     * @return object[] Each row has entity_id and view_count, ordered by count DESC.
     */
    public function getTopEntities(string $eventType, string $startDate, string $endDate, int $limit = 10): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT entity_id, COUNT(*) AS view_count
                 FROM {$this->table}
                 WHERE event_type = %s
                   AND created_at >= %s
                   AND created_at < %s
                   AND entity_id IS NOT NULL
                 GROUP BY entity_id
                 ORDER BY view_count DESC
                 LIMIT %d",
                $eventType,
                $startDate,
                $endDate,
                $limit
            )
        );
    }

    /**
     * Get recent events for a specific entity.
     *
     * @return object[] Events ordered by most recent first.
     */
    public function getRecentByEntity(string $entityId, string $entityType, int $limit = 50): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE entity_id = %s
                   AND entity_type = %s
                 ORDER BY created_at DESC
                 LIMIT %d",
                $entityId,
                $entityType,
                $limit
            )
        );
    }
}

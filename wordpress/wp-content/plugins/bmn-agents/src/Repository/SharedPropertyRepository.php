<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_shared_properties.
 */
class SharedPropertyRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_shared_properties';
    }

    /** Override: this table uses shared_at instead of created_at. */
    protected bool $timestamps = false;

    public function create(array $data): int|false
    {
        $now = current_time('mysql');
        $data['shared_at'] = $data['shared_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;

        $result = $this->wpdb->insert($this->table, $data);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    public function update(int|string $id, array $data): bool
    {
        $data['updated_at'] = $data['updated_at'] ?? current_time('mysql');

        $result = $this->wpdb->update(
            $this->table,
            $data,
            [$this->primaryKey => $id]
        );

        return $result !== false;
    }

    /**
     * Find an existing share by agent, client, and listing.
     */
    public function findByAgentClientListing(int $agentUserId, int $clientUserId, string $listingId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE agent_user_id = %d AND client_user_id = %d AND listing_id = %s
                 LIMIT 1",
                $agentUserId,
                $clientUserId,
                $listingId
            )
        );

        return $result ?: null;
    }

    /**
     * Get shared properties for a client (optionally excluding dismissed).
     *
     * @return object[]
     */
    public function findForClient(int $clientUserId, bool $includeDismissed = false, int $limit = 0, int $offset = 0): array
    {
        $where = ['client_user_id = %d'];
        $values = [$clientUserId];

        if (!$includeDismissed) {
            $where[] = 'is_dismissed = 0';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY shared_at DESC";

        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = $limit;
            $values[] = $offset;
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$values)
        ) ?? [];
    }

    /**
     * Count shared properties for a client.
     */
    public function countForClient(int $clientUserId, bool $includeDismissed = false): int
    {
        $where = ['client_user_id = %d'];
        $values = [$clientUserId];

        if (!$includeDismissed) {
            $where[] = 'is_dismissed = 0';
        }

        $whereClause = implode(' AND ', $where);

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}",
                ...$values
            )
        );
    }

    /**
     * Get shared properties sent by an agent.
     *
     * @return object[]
     */
    public function findByAgent(int $agentUserId, int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE agent_user_id = %d ORDER BY shared_at DESC";
        $values = [$agentUserId];

        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = $limit;
            $values[] = $offset;
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$values)
        ) ?? [];
    }

    /**
     * Increment view count and set first_viewed_at if null.
     */
    public function recordView(int $id): bool
    {
        $now = current_time('mysql');

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table}
                 SET view_count = view_count + 1,
                     first_viewed_at = COALESCE(first_viewed_at, %s),
                     updated_at = %s
                 WHERE id = %d",
                $now,
                $now,
                $id
            )
        );

        return $result !== false;
    }
}

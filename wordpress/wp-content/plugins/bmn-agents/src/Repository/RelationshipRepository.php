<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_agent_client_relationships.
 */
class RelationshipRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_agent_client_relationships';
    }

    /** Override: this table uses assigned_at instead of created_at. */
    protected bool $timestamps = false;

    /**
     * @return int|false
     */
    public function create(array $data): int|false
    {
        $now = current_time('mysql');
        $data['assigned_at'] = $data['assigned_at'] ?? $now;
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
     * Find the active agent for a client.
     */
    public function findActiveForClient(int $clientUserId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE client_user_id = %d AND status = 'active'
                 LIMIT 1",
                $clientUserId
            )
        );

        return $result ?: null;
    }

    /**
     * Find a specific relationship between agent and client.
     */
    public function findByAgentAndClient(int $agentUserId, int $clientUserId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE agent_user_id = %d AND client_user_id = %d
                 LIMIT 1",
                $agentUserId,
                $clientUserId
            )
        );

        return $result ?: null;
    }

    /**
     * Get clients for an agent with optional status filter.
     *
     * @return object[]
     */
    public function findClientsByAgent(int $agentUserId, ?string $status = null, int $limit = 0, int $offset = 0): array
    {
        $where = ['agent_user_id = %d'];
        $values = [$agentUserId];

        if ($status !== null) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY assigned_at DESC";

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
     * Count clients for an agent with optional status filter.
     */
    public function countClientsByAgent(int $agentUserId, ?string $status = null): int
    {
        $where = ['agent_user_id = %d'];
        $values = [$agentUserId];

        if ($status !== null) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $whereClause = implode(' AND ', $where);

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}",
                ...$values
            )
        );
    }
}

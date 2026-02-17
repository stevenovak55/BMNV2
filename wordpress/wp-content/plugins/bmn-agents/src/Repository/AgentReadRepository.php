<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use wpdb;

/**
 * Read-only repository for the extractor's bmn_agents table.
 *
 * This plugin never writes to bmn_agents — that table is owned by bmn-extractor.
 */
class AgentReadRepository
{
    protected readonly wpdb $wpdb;
    protected readonly string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bmn_agents';
    }

    public function findByMlsId(string $agentMlsId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE agent_mls_id = %s LIMIT 1",
                $agentMlsId
            )
        );

        return $result ?: null;
    }

    /**
     * @return object[]
     */
    public function findAll(int $limit = 0, int $offset = 0, string $orderBy = 'full_name', string $order = 'ASC'): array
    {
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $orderBy = preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy);

        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$order}";

        if ($limit > 0) {
            $sql = $this->wpdb->prepare(
                $sql . ' LIMIT %d OFFSET %d',
                $limit,
                $offset
            );
        }

        return $this->wpdb->get_results($sql) ?? [];
    }

    /**
     * Search agents by name.
     *
     * @return object[]
     */
    public function searchByName(string $term, int $limit = 20): array
    {
        $like = '%' . $this->wpdb->esc_like($term) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE full_name LIKE %s
                 ORDER BY full_name ASC
                 LIMIT %d",
                $like,
                $limit
            )
        ) ?? [];
    }

    public function count(): int
    {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }
}

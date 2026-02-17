<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use wpdb;

/**
 * Read-only repository for the extractor's bmn_offices table.
 *
 * This plugin never writes to bmn_offices — that table is owned by bmn-extractor.
 */
class OfficeReadRepository
{
    protected readonly wpdb $wpdb;
    protected readonly string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bmn_offices';
    }

    public function findByMlsId(string $officeMlsId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE office_mls_id = %s LIMIT 1",
                $officeMlsId
            )
        );

        return $result ?: null;
    }

    /**
     * @return object[]
     */
    public function findAll(int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY office_name ASC";

        if ($limit > 0) {
            $sql = $this->wpdb->prepare(
                $sql . ' LIMIT %d OFFSET %d',
                $limit,
                $offset
            );
        }

        return $this->wpdb->get_results($sql) ?? [];
    }

    public function count(): int
    {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }
}

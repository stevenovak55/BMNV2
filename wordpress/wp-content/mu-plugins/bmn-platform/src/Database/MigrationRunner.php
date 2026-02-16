<?php

declare(strict_types=1);

namespace BMN\Platform\Database;

use wpdb;

/**
 * Runs and tracks database migrations.
 *
 * Migration state is stored in the `{prefix}bmn_migrations` table.
 *
 * Usage:
 *
 *     $runner = new MigrationRunner($wpdb);
 *     $runner->run([
 *         new CreatePropertiesTable(),
 *         new AddSchoolGradeColumn(),
 *     ]);
 */
final class MigrationRunner
{
    private readonly wpdb $wpdb;

    /** Fully-qualified migration-tracking table name (with prefix). */
    private readonly string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bmn_migrations';
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Run all pending migrations in the given list.
     *
     * Migrations whose version already exists in the tracking table are
     * skipped. Each successful migration is recorded with a timestamp.
     *
     * @param Migration[] $migrations Ordered list of migration instances.
     *
     * @return string[] Versions that were applied during this run.
     */
    public function run(array $migrations): array
    {
        $this->ensureTable();

        $applied = $this->getAppliedVersions();
        $ran = [];

        foreach ($migrations as $migration) {
            $version = $migration->getVersion();

            if (in_array($version, $applied, true)) {
                continue;
            }

            $migration->up();

            $this->wpdb->insert(
                $this->table,
                [
                    'version'    => $version,
                    'applied_at' => current_time('mysql'),
                ],
                ['%s', '%s']
            );

            $ran[] = $version;
        }

        return $ran;
    }

    /**
     * Roll back the last batch of migrations (or a specific count).
     *
     * @param Migration[] $migrations Full ordered list of migrations (newest last).
     * @param int         $steps      Number of migrations to roll back (default 1).
     *
     * @return string[] Versions that were rolled back.
     */
    public function rollback(array $migrations, int $steps = 1): array
    {
        $this->ensureTable();

        // Build a lookup by version.
        $lookup = [];
        foreach ($migrations as $migration) {
            $lookup[$migration->getVersion()] = $migration;
        }

        // Get the most recently applied versions.
        $recent = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT version FROM {$this->table} ORDER BY applied_at DESC LIMIT %d",
                $steps
            )
        );

        $rolledBack = [];

        foreach ($recent as $version) {
            if (! isset($lookup[$version])) {
                continue;
            }

            $lookup[$version]->down();

            $this->wpdb->delete(
                $this->table,
                ['version' => $version],
                ['%s']
            );

            $rolledBack[] = $version;
        }

        return $rolledBack;
    }

    /**
     * Return the current migration status.
     *
     * @param Migration[] $migrations Full ordered list of migrations.
     *
     * @return array<int, array{version: string, applied: bool, applied_at: string|null}>
     */
    public function status(array $migrations): array
    {
        $this->ensureTable();

        $applied = $this->getAppliedMap();
        $status = [];

        foreach ($migrations as $migration) {
            $version = $migration->getVersion();
            $status[] = [
                'version'    => $version,
                'applied'    => isset($applied[$version]),
                'applied_at' => $applied[$version] ?? null,
            ];
        }

        return $status;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Create the migration tracking table if it does not exist.
     */
    private function ensureTable(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            version VARCHAR(255) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY version (version)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get a flat list of already-applied version strings.
     *
     * @return string[]
     */
    private function getAppliedVersions(): array
    {
        return $this->wpdb->get_col("SELECT version FROM {$this->table} ORDER BY applied_at ASC");
    }

    /**
     * Get a map of version => applied_at for quick lookups.
     *
     * @return array<string, string>
     */
    private function getAppliedMap(): array
    {
        $rows = $this->wpdb->get_results("SELECT version, applied_at FROM {$this->table}", ARRAY_A);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['version']] = $row['applied_at'];
        }

        return $map;
    }
}

<?php

declare(strict_types=1);

namespace BMN\Platform\Database;

/**
 * Abstract base for database migrations.
 *
 * Each concrete migration represents a single schema change and must be
 * idempotent -- running the same migration twice should not cause errors.
 *
 * Naming convention: YYYY_MM_DD_HHMMSS_description.php
 * Example class:     Migration_2026_02_16_000000_CreatePropertiesTable
 */
abstract class Migration
{
    /**
     * Apply the migration.
     *
     * Use $wpdb and dbDelta() (or raw SQL) to create/alter tables.
     */
    abstract public function up(): void;

    /**
     * Reverse the migration.
     *
     * Drop tables or undo schema changes applied in up().
     */
    abstract public function down(): void;

    /**
     * Return the version identifier for this migration.
     *
     * The runner uses this value to track which migrations have been applied.
     * By default the short class name is used (e.g. "Migration_2026_02_16_000000_CreatePropertiesTable").
     */
    public function getVersion(): string
    {
        $fqcn = static::class;
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}

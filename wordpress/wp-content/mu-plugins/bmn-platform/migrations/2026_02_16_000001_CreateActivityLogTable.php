<?php

declare(strict_types=1);

namespace BMN\Platform\Migrations;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_activity_log table for structured activity logging.
 *
 * Stores API requests, user actions, extraction runs, notification
 * deliveries, cron executions, and other auditable events.
 *
 * Retention: 90 days (handled by a scheduled cleanup job).
 */
final class CreateActivityLogTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_activity_log';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NOT NULL DEFAULT '',
            entity_id VARCHAR(50) NOT NULL DEFAULT '',
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(500) NOT NULL DEFAULT '',
            context JSON DEFAULT NULL,
            duration_ms INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_action (action),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_created_at (created_at),
            KEY idx_user_action (user_id, action, created_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_activity_log';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

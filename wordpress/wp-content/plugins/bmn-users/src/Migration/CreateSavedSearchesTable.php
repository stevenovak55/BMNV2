<?php

declare(strict_types=1);

namespace BMN\Users\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_user_saved_searches table.
 */
final class CreateSavedSearchesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_user_saved_searches';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            filters JSON NOT NULL,
            polygon_shapes JSON DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_alert_at DATETIME DEFAULT NULL,
            result_count INT UNSIGNED DEFAULT 0,
            new_count INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_user_id (user_id),
            KEY idx_active_alert (is_active, last_alert_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_user_saved_searches';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

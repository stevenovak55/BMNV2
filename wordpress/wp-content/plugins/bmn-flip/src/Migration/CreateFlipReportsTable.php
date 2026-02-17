<?php

declare(strict_types=1);

namespace BMN\Flip\Migration;

use BMN\Platform\Database\Migration;

final class CreateFlipReportsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_reports';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'manual',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            cities JSON DEFAULT NULL,
            filters JSON DEFAULT NULL,
            last_run_date DATETIME DEFAULT NULL,
            run_count INT UNSIGNED DEFAULT 0,
            property_count INT UNSIGNED DEFAULT 0,
            viable_count INT UNSIGNED DEFAULT 0,
            is_favorite TINYINT(1) DEFAULT 0,
            monitor_frequency VARCHAR(20) DEFAULT NULL,
            monitor_last_check DATETIME DEFAULT NULL,
            notification_level VARCHAR(20) DEFAULT 'viable_only',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_user_id (user_id),
            KEY idx_type (type),
            KEY idx_status (status),
            KEY idx_is_favorite (is_favorite)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_reports';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

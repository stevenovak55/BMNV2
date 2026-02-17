<?php

declare(strict_types=1);

namespace BMN\Flip\Migration;

use BMN\Platform\Database\Migration;

final class CreateMonitorSeenTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_monitor_seen';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            listing_id VARCHAR(50) NOT NULL,
            first_seen_date DATETIME NOT NULL,
            last_score DECIMAL(5,1) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_report_listing (report_id, listing_id),
            KEY idx_report_id (report_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_monitor_seen';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

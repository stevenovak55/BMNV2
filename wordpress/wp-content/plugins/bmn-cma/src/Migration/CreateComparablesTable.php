<?php

declare(strict_types=1);

namespace BMN\CMA\Migration;

use BMN\Platform\Database\Migration;

final class CreateComparablesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_comparables';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            listing_id VARCHAR(50) NOT NULL,
            close_price DECIMAL(12,2) DEFAULT 0,
            adjusted_price DECIMAL(12,2) DEFAULT NULL,
            adjustment_total DECIMAL(12,2) DEFAULT 0,
            adjustments JSON DEFAULT NULL,
            comparability_score DECIMAL(5,2) DEFAULT NULL,
            comparability_grade CHAR(1) DEFAULT NULL,
            distance_miles DECIMAL(6,2) DEFAULT NULL,
            property_data JSON DEFAULT NULL,
            is_selected TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_report_id (report_id),
            KEY idx_listing_id (listing_id),
            UNIQUE KEY report_listing (report_id, listing_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_comparables';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

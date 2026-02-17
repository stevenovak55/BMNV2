<?php

declare(strict_types=1);

namespace BMN\CMA\Migration;

use BMN\Platform\Database\Migration;

final class CreateValueHistoryTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_cma_value_history';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            listing_id VARCHAR(50) DEFAULT NULL,
            property_address VARCHAR(500) DEFAULT NULL,
            report_id BIGINT UNSIGNED DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT 0,
            estimated_value_low DECIMAL(12,2) DEFAULT NULL,
            estimated_value_mid DECIMAL(12,2) DEFAULT NULL,
            estimated_value_high DECIMAL(12,2) DEFAULT NULL,
            comparables_count INT UNSIGNED DEFAULT 0,
            confidence_score DECIMAL(5,1) DEFAULT NULL,
            confidence_level VARCHAR(20) DEFAULT NULL,
            avg_price_per_sqft DECIMAL(10,2) DEFAULT NULL,
            is_arv_mode TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_listing_id (listing_id),
            KEY idx_user_id (user_id),
            KEY idx_property_address (property_address(191))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_cma_value_history';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

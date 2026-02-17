<?php

declare(strict_types=1);

namespace BMN\Flip\Migration;

use BMN\Platform\Database\Migration;

final class CreateFlipComparablesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_comparables';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            analysis_id BIGINT UNSIGNED NOT NULL,
            listing_id VARCHAR(50) NOT NULL,
            address VARCHAR(500) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            close_price DECIMAL(12,2) DEFAULT NULL,
            close_date DATE DEFAULT NULL,
            adjusted_price DECIMAL(12,2) DEFAULT NULL,
            adjustment_total DECIMAL(12,2) DEFAULT NULL,
            adjustments JSON DEFAULT NULL,
            distance_miles DECIMAL(6,3) DEFAULT NULL,
            property_type VARCHAR(50) DEFAULT NULL,
            bedrooms_total INT UNSIGNED DEFAULT NULL,
            bathrooms_total DECIMAL(4,1) DEFAULT NULL,
            living_area INT UNSIGNED DEFAULT NULL,
            year_built INT UNSIGNED DEFAULT NULL,
            lot_size_acres DECIMAL(8,4) DEFAULT NULL,
            garage_spaces INT UNSIGNED DEFAULT 0,
            days_on_market INT UNSIGNED DEFAULT NULL,
            weight DECIMAL(8,4) DEFAULT NULL,
            is_renovated TINYINT(1) DEFAULT 0,
            is_distressed TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_analysis_id (analysis_id),
            KEY idx_listing_id (listing_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_comparables';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\CMA\Migration;

use BMN\Platform\Database\Migration;

final class CreateMarketSnapshotsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_market_snapshots';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            city VARCHAR(100) NOT NULL,
            property_type VARCHAR(50) DEFAULT 'all',
            snapshot_date DATE NOT NULL,
            active_listings INT UNSIGNED DEFAULT 0,
            new_listings INT UNSIGNED DEFAULT 0,
            closed_sales INT UNSIGNED DEFAULT 0,
            median_price DECIMAL(12,2) DEFAULT NULL,
            avg_price DECIMAL(12,2) DEFAULT NULL,
            median_dom INT UNSIGNED DEFAULT NULL,
            avg_dom INT UNSIGNED DEFAULT NULL,
            median_price_per_sqft DECIMAL(10,2) DEFAULT NULL,
            months_supply DECIMAL(4,1) DEFAULT NULL,
            list_to_sale_ratio DECIMAL(5,3) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY city_type_date (city, property_type, snapshot_date),
            KEY idx_snapshot_date (snapshot_date)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_market_snapshots';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\Analytics\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_analytics_daily table.
 *
 * Stores pre-aggregated daily metrics to enable fast dashboard queries
 * without scanning the full events table.
 */
final class CreateDailyAggregatesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_analytics_daily';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            aggregate_date DATE NOT NULL,
            metric_type VARCHAR(50) NOT NULL,
            metric_value INT UNSIGNED NOT NULL DEFAULT 0,
            dimension VARCHAR(100) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_date_type_dimension (aggregate_date, metric_type, dimension(100)),
            KEY idx_metric_type (metric_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_analytics_daily';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

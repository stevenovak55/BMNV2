<?php

declare(strict_types=1);

namespace BMN\Extractor\Migrations;

use BMN\Platform\Database\Migration;

class CreateExtractionsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_extractions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            extraction_type VARCHAR(20) NOT NULL DEFAULT 'incremental',
            triggered_by VARCHAR(50) NOT NULL DEFAULT 'cron',
            status VARCHAR(20) NOT NULL DEFAULT 'running',
            listings_processed INT NOT NULL DEFAULT 0,
            listings_created INT NOT NULL DEFAULT 0,
            listings_updated INT NOT NULL DEFAULT 0,
            listings_archived INT NOT NULL DEFAULT 0,
            errors_count INT NOT NULL DEFAULT 0,
            last_listing_key VARCHAR(128) NULL,
            last_modification_timestamp DATETIME NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_started (started_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_extractions';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\Extractor\Migrations;

use BMN\Platform\Database\Migration;

class CreateOpenHousesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_open_houses';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_key VARCHAR(128) NOT NULL,
            open_house_key VARCHAR(128) NULL,
            open_house_date DATE NULL,
            open_house_start_time TIME NULL,
            open_house_end_time TIME NULL,
            open_house_type VARCHAR(50) NULL,
            open_house_remarks TEXT NULL,
            showing_agent_mls_id VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_listing (listing_key),
            KEY idx_date (open_house_date)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_open_houses';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

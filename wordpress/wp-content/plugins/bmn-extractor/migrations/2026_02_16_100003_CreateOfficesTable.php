<?php

declare(strict_types=1);

namespace BMN\Extractor\Migrations;

use BMN\Platform\Database\Migration;

class CreateOfficesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_offices';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            office_mls_id VARCHAR(50) NOT NULL,
            office_key VARCHAR(128) NULL,
            office_name VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            address VARCHAR(255) NULL,
            city VARCHAR(100) NULL,
            state_or_province VARCHAR(50) NULL,
            postal_code VARCHAR(20) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_office_mls_id (office_mls_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_offices';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

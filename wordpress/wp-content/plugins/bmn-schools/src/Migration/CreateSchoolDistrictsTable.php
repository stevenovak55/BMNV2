<?php

declare(strict_types=1);

namespace BMN\Schools\Migration;

use BMN\Platform\Database\Migration;

final class CreateSchoolDistrictsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_districts';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nces_district_id VARCHAR(20) NOT NULL,
            state_district_id VARCHAR(20) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            county VARCHAR(100) DEFAULT NULL,
            total_schools INT UNSIGNED DEFAULT 0,
            total_students INT UNSIGNED DEFAULT 0,
            boundary_geojson LONGTEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            extra_data JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY nces_district_id (nces_district_id),
            KEY idx_city (city),
            KEY idx_county (county)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_districts';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

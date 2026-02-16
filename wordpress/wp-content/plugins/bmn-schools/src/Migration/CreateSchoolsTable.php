<?php

declare(strict_types=1);

namespace BMN\Schools\Migration;

use BMN\Platform\Database\Migration;

final class CreateSchoolsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_schools';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nces_school_id VARCHAR(20) NOT NULL,
            state_school_id VARCHAR(20) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            level ENUM('Elementary','Middle','High','Other') NOT NULL DEFAULT 'Other',
            school_type ENUM('public','private','charter') NOT NULL DEFAULT 'public',
            grades_low VARCHAR(5) DEFAULT NULL,
            grades_high VARCHAR(5) DEFAULT NULL,
            district_id BIGINT UNSIGNED DEFAULT NULL,
            address VARCHAR(255) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            state VARCHAR(2) DEFAULT 'MA',
            zip VARCHAR(10) DEFAULT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY nces_school_id (nces_school_id),
            KEY idx_district_id (district_id),
            KEY idx_city_level (city, level),
            KEY idx_lat_lng (latitude, longitude),
            KEY idx_level (level)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_schools';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

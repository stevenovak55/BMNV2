<?php

declare(strict_types=1);

namespace BMN\Schools\Migration;

use BMN\Platform\Database\Migration;

final class CreateDistrictRankingsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_district_rankings';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            district_id BIGINT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            composite_score DECIMAL(6,2) DEFAULT NULL,
            percentile_rank DECIMAL(5,2) DEFAULT NULL,
            state_rank INT UNSIGNED DEFAULT NULL,
            letter_grade VARCHAR(2) DEFAULT NULL,
            schools_count INT UNSIGNED DEFAULT 0,
            schools_with_data INT UNSIGNED DEFAULT 0,
            elementary_avg DECIMAL(6,2) DEFAULT NULL,
            middle_avg DECIMAL(6,2) DEFAULT NULL,
            high_avg DECIMAL(6,2) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY idx_district_year (district_id, year),
            KEY idx_composite_score (composite_score),
            KEY idx_year (year)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_district_rankings';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

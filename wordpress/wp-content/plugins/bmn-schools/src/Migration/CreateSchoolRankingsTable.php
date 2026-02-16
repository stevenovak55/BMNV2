<?php

declare(strict_types=1);

namespace BMN\Schools\Migration;

use BMN\Platform\Database\Migration;

final class CreateSchoolRankingsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_rankings';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            category VARCHAR(50) DEFAULT NULL,
            composite_score DECIMAL(6,2) DEFAULT NULL,
            percentile_rank DECIMAL(5,2) DEFAULT NULL,
            state_rank INT UNSIGNED DEFAULT NULL,
            letter_grade VARCHAR(2) DEFAULT NULL,
            mcas_score DECIMAL(6,2) DEFAULT NULL,
            graduation_score DECIMAL(6,2) DEFAULT NULL,
            masscore_score DECIMAL(6,2) DEFAULT NULL,
            attendance_score DECIMAL(6,2) DEFAULT NULL,
            ap_score DECIMAL(6,2) DEFAULT NULL,
            growth_score DECIMAL(6,2) DEFAULT NULL,
            spending_score DECIMAL(6,2) DEFAULT NULL,
            ratio_score DECIMAL(6,2) DEFAULT NULL,
            data_components INT UNSIGNED DEFAULT 0,
            confidence_level VARCHAR(20) DEFAULT NULL,
            calculated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY idx_school_year (school_id, year),
            KEY idx_composite_score (composite_score),
            KEY idx_year_category (year, category),
            KEY idx_letter_grade (letter_grade)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_rankings';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

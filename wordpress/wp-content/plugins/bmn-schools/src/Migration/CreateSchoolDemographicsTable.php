<?php

declare(strict_types=1);

namespace BMN\Schools\Migration;

use BMN\Platform\Database\Migration;

final class CreateSchoolDemographicsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_demographics';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            total_students INT UNSIGNED DEFAULT NULL,
            pct_male DECIMAL(5,2) DEFAULT NULL,
            pct_female DECIMAL(5,2) DEFAULT NULL,
            pct_white DECIMAL(5,2) DEFAULT NULL,
            pct_black DECIMAL(5,2) DEFAULT NULL,
            pct_hispanic DECIMAL(5,2) DEFAULT NULL,
            pct_asian DECIMAL(5,2) DEFAULT NULL,
            pct_free_reduced_lunch DECIMAL(5,2) DEFAULT NULL,
            pct_english_learner DECIMAL(5,2) DEFAULT NULL,
            pct_special_ed DECIMAL(5,2) DEFAULT NULL,
            avg_class_size DECIMAL(5,1) DEFAULT NULL,
            teacher_count INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY idx_school_year (school_id, year),
            KEY idx_year (year)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_demographics';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

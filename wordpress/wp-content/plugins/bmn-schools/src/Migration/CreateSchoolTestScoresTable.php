<?php

declare(strict_types=1);

namespace BMN\Schools\Migration;

use BMN\Platform\Database\Migration;

final class CreateSchoolTestScoresTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_test_scores';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            grade VARCHAR(10) DEFAULT NULL,
            subject VARCHAR(50) NOT NULL,
            test_name VARCHAR(100) DEFAULT 'MCAS',
            students_tested INT UNSIGNED DEFAULT NULL,
            proficient_or_above_pct DECIMAL(5,2) DEFAULT NULL,
            advanced_pct DECIMAL(5,2) DEFAULT NULL,
            proficient_pct DECIMAL(5,2) DEFAULT NULL,
            avg_scaled_score DECIMAL(8,2) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_school_year (school_id, year),
            KEY idx_year_subject (year, subject)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_test_scores';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

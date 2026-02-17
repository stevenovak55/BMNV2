<?php

declare(strict_types=1);

namespace BMN\CMA\Migration;

use BMN\Platform\Database\Migration;

final class CreateCmaReportsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_cma_reports';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            session_name VARCHAR(255) DEFAULT NULL,
            subject_listing_id VARCHAR(50) DEFAULT NULL,
            subject_address VARCHAR(500) DEFAULT NULL,
            subject_city VARCHAR(100) DEFAULT NULL,
            subject_state VARCHAR(2) DEFAULT 'MA',
            subject_zip VARCHAR(10) DEFAULT NULL,
            subject_data JSON DEFAULT NULL,
            subject_overrides JSON DEFAULT NULL,
            cma_filters JSON DEFAULT NULL,
            comparables_count INT UNSIGNED DEFAULT 0,
            estimated_value_low DECIMAL(12,2) DEFAULT NULL,
            estimated_value_mid DECIMAL(12,2) DEFAULT NULL,
            estimated_value_high DECIMAL(12,2) DEFAULT NULL,
            confidence_score DECIMAL(5,1) DEFAULT NULL,
            confidence_level VARCHAR(20) DEFAULT NULL,
            summary_statistics JSON DEFAULT NULL,
            is_favorite TINYINT(1) DEFAULT 0,
            is_arv_mode TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_user_id (user_id),
            KEY idx_subject_listing_id (subject_listing_id),
            KEY idx_is_favorite (is_favorite)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_cma_reports';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

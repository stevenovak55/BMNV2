<?php

declare(strict_types=1);

namespace BMN\Schools\Migration;

use BMN\Platform\Database\Migration;

final class CreateSchoolFeaturesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_features';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT UNSIGNED NOT NULL,
            feature_type VARCHAR(50) NOT NULL,
            feature_name VARCHAR(100) NOT NULL,
            feature_value JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_school_type (school_id, feature_type),
            KEY idx_feature_type (feature_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bmn_school_features';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

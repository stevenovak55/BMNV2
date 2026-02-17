<?php

declare(strict_types=1);

namespace BMN\Appointments\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_staff table.
 */
final class CreateStaffTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_staff';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            google_refresh_token TEXT DEFAULT NULL,
            google_access_token TEXT DEFAULT NULL,
            google_token_expires DATETIME DEFAULT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_user_id (user_id),
            KEY idx_is_active (is_active),
            KEY idx_is_primary (is_primary)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_staff';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

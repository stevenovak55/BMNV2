<?php

declare(strict_types=1);

namespace BMN\Appointments\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_appointment_types table.
 */
final class CreateAppointmentTypesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_appointment_types';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
            buffer_before INT UNSIGNED NOT NULL DEFAULT 0,
            buffer_after INT UNSIGNED NOT NULL DEFAULT 0,
            color VARCHAR(7) DEFAULT '#3B82F6',
            requires_approval TINYINT(1) NOT NULL DEFAULT 0,
            requires_login TINYINT(1) NOT NULL DEFAULT 0,
            custom_fields JSON DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY unique_slug (slug),
            KEY idx_is_active (is_active),
            KEY idx_sort_order (sort_order)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_appointment_types';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

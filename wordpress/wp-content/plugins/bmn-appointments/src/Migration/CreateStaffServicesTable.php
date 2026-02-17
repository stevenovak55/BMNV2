<?php

declare(strict_types=1);

namespace BMN\Appointments\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_staff_services junction table.
 */
final class CreateStaffServicesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_staff_services';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT UNSIGNED NOT NULL,
            appointment_type_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY unique_staff_type (staff_id, appointment_type_id),
            KEY idx_staff_id (staff_id),
            KEY idx_appointment_type_id (appointment_type_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_staff_services';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

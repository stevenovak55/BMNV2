<?php

declare(strict_types=1);

namespace BMN\Appointments\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_appointment_attendees table.
 */
final class CreateAttendeeTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_appointment_attendees';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            appointment_id BIGINT UNSIGNED NOT NULL,
            attendee_type ENUM('primary','additional','cc') NOT NULL DEFAULT 'primary',
            user_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            reminder_24h_sent TINYINT(1) NOT NULL DEFAULT 0,
            reminder_1h_sent TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_appointment_id (appointment_id),
            KEY idx_user_id (user_id),
            KEY idx_attendee_type (attendee_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_appointment_attendees';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\Appointments\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_appointments table.
 */
final class CreateAppointmentsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_appointments';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT UNSIGNED NOT NULL,
            appointment_type_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
            appointment_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            client_name VARCHAR(255) NOT NULL,
            client_email VARCHAR(255) NOT NULL,
            client_phone VARCHAR(50) DEFAULT NULL,
            listing_id VARCHAR(20) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            google_event_id VARCHAR(255) DEFAULT NULL,
            cancellation_reason TEXT DEFAULT NULL,
            cancelled_by ENUM('client','staff','system') DEFAULT NULL,
            reschedule_count INT UNSIGNED NOT NULL DEFAULT 0,
            original_datetime DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY unique_slot (staff_id, appointment_date, start_time),
            KEY idx_user_id (user_id),
            KEY idx_staff_id (staff_id),
            KEY idx_appointment_type_id (appointment_type_id),
            KEY idx_status (status),
            KEY idx_appointment_date (appointment_date),
            KEY idx_listing_id (listing_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_appointments';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

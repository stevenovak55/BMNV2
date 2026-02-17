<?php

declare(strict_types=1);

namespace BMN\Appointments\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_notifications_log table.
 */
final class CreateNotificationsLogTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_notifications_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            appointment_id BIGINT UNSIGNED NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            recipient_type ENUM('client','staff','admin') NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
            error_message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_appointment_id (appointment_id),
            KEY idx_notification_type (notification_type),
            KEY idx_status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_notifications_log';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

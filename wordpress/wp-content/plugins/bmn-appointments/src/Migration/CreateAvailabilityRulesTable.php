<?php

declare(strict_types=1);

namespace BMN\Appointments\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_availability_rules table.
 */
final class CreateAvailabilityRulesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_availability_rules';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT UNSIGNED NOT NULL,
            rule_type ENUM('recurring','specific_date','blocked') NOT NULL DEFAULT 'recurring',
            day_of_week TINYINT UNSIGNED DEFAULT NULL COMMENT '0=Sunday, 6=Saturday',
            specific_date DATE DEFAULT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            appointment_type_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_staff_id (staff_id),
            KEY idx_rule_type (rule_type),
            KEY idx_specific_date (specific_date),
            KEY idx_appointment_type_id (appointment_type_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_availability_rules';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

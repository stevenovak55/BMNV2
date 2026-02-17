<?php

declare(strict_types=1);

namespace BMN\Agents\Migration;

use BMN\Platform\Database\Migration;

final class CreateActivityLogTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_activity_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(100) DEFAULT NULL,
            entity_type VARCHAR(50) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            notification_sent TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_agent_created (agent_user_id, created_at),
            KEY idx_client (client_user_id),
            KEY idx_type (activity_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_activity_log';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\Agents\Migration;

use BMN\Platform\Database\Migration;

final class CreateRelationshipsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_client_relationships';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            status ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
            source ENUM('manual','referral','organic','claimed') NOT NULL DEFAULT 'manual',
            notes TEXT DEFAULT NULL,
            assigned_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_agent_client (agent_user_id, client_user_id),
            KEY idx_client (client_user_id),
            KEY idx_agent_status (agent_user_id, status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_client_relationships';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

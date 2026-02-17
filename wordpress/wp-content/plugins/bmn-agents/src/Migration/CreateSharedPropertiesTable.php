<?php

declare(strict_types=1);

namespace BMN\Agents\Migration;

use BMN\Platform\Database\Migration;

final class CreateSharedPropertiesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_shared_properties';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            listing_id VARCHAR(50) NOT NULL,
            agent_note TEXT DEFAULT NULL,
            client_response ENUM('none','interested','not_interested') NOT NULL DEFAULT 'none',
            client_note TEXT DEFAULT NULL,
            is_dismissed TINYINT(1) NOT NULL DEFAULT 0,
            view_count INT UNSIGNED NOT NULL DEFAULT 0,
            first_viewed_at DATETIME DEFAULT NULL,
            shared_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_agent_client_listing (agent_user_id, client_user_id, listing_id),
            KEY idx_client_dismissed (client_user_id, is_dismissed),
            KEY idx_listing (listing_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_shared_properties';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\Agents\Migration;

use BMN\Platform\Database\Migration;

final class CreateAgentProfilesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_profiles';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agent_mls_id VARCHAR(50) NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            photo_url VARCHAR(500) DEFAULT NULL,
            specialties TEXT DEFAULT NULL,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            snab_staff_id BIGINT UNSIGNED DEFAULT NULL,
            display_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_agent_mls_id (agent_mls_id),
            KEY idx_user_id (user_id),
            KEY idx_featured_active (is_featured, is_active)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_profiles';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

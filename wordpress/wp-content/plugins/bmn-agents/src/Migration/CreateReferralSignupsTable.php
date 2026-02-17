<?php

declare(strict_types=1);

namespace BMN\Agents\Migration;

use BMN\Platform\Database\Migration;

final class CreateReferralSignupsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_referral_signups';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_user_id BIGINT UNSIGNED NOT NULL,
            agent_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            referral_code VARCHAR(50) DEFAULT NULL,
            signup_source ENUM('organic','referral_link','agent_created') NOT NULL DEFAULT 'organic',
            platform ENUM('web','ios','admin') NOT NULL DEFAULT 'web',
            created_at DATETIME NOT NULL,
            UNIQUE KEY uk_client (client_user_id),
            KEY idx_agent (agent_user_id),
            KEY idx_code (referral_code)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_referral_signups';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

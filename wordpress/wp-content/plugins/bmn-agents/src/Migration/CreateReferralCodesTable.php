<?php

declare(strict_types=1);

namespace BMN\Agents\Migration;

use BMN\Platform\Database\Migration;

final class CreateReferralCodesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_referral_codes';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            referral_code VARCHAR(50) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_referral_code (referral_code),
            KEY idx_agent (agent_user_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_agent_referral_codes';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

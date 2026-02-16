<?php

declare(strict_types=1);

namespace BMN\Users\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_password_resets table.
 */
final class CreatePasswordResetsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_password_resets';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            KEY idx_user_id (user_id),
            KEY idx_token_hash (token_hash),
            KEY idx_expires_at (expires_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_password_resets';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

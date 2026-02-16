<?php

declare(strict_types=1);

namespace BMN\Users\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_revoked_tokens table.
 */
final class CreateRevokedTokensTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_revoked_tokens';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token_hash VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            revoked_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            UNIQUE KEY token_hash (token_hash),
            KEY idx_expires_at (expires_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_revoked_tokens';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

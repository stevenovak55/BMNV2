<?php

declare(strict_types=1);

namespace BMN\Users\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_user_favorites table.
 */
final class CreateFavoritesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_user_favorites';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            listing_id VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY user_listing (user_id, listing_id),
            KEY idx_user_id (user_id),
            KEY idx_listing_id (listing_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_user_favorites';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

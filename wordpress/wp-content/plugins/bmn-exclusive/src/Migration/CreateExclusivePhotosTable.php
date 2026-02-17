<?php

declare(strict_types=1);

namespace BMN\Exclusive\Migration;

use BMN\Platform\Database\Migration;

final class CreateExclusivePhotosTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_exclusive_photos';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            exclusive_listing_id BIGINT UNSIGNED NOT NULL,
            media_url VARCHAR(500) NOT NULL,
            sort_order TINYINT UNSIGNED DEFAULT 0,
            is_primary TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_exclusive_listing_id (exclusive_listing_id),
            KEY idx_sort_order (exclusive_listing_id, sort_order)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_exclusive_photos';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

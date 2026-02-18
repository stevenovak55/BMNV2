<?php

declare(strict_types=1);

namespace BMN\Extractor\Migrations;

use BMN\Platform\Database\Migration;

/**
 * Create the bmn_rooms table for per-room detail data.
 *
 * V1 has bme_rooms (17K rows) with room_type, level, dimensions, features.
 * V2 previously stored room data only in extra_data JSON. This migration
 * adds a dedicated table for room-level queries and display.
 *
 * Session 24, Fix 9.
 */
class CreateRoomsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_rooms';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_key VARCHAR(50) NOT NULL,
            room_type VARCHAR(100) NOT NULL,
            room_level VARCHAR(50) NULL,
            room_dimensions VARCHAR(50) NULL,
            room_area DECIMAL(10,2) NULL,
            room_description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_listing_key (listing_key),
            KEY idx_room_type (room_type, room_level)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_rooms';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

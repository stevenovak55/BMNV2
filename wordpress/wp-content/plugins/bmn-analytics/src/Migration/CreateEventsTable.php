<?php

declare(strict_types=1);

namespace BMN\Analytics\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_analytics_events table.
 *
 * Stores individual tracking events: pageviews, property views, searches,
 * favorites, CMA generations, and other user interactions.
 */
final class CreateEventsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_analytics_events';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            event_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(100) DEFAULT NULL,
            entity_type VARCHAR(50) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            referrer VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_session_id (session_id),
            KEY idx_user_id (user_id),
            KEY idx_event_type (event_type),
            KEY idx_entity_id (entity_id),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_analytics_events';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

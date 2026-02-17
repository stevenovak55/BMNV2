<?php

declare(strict_types=1);

namespace BMN\Analytics\Migration;

use BMN\Platform\Database\Migration;

/**
 * Creates the bmn_analytics_sessions table.
 *
 * Tracks visitor sessions with device info, traffic source, and engagement
 * metrics (page views, events count).
 */
final class CreateSessionsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_analytics_sessions';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            device_type VARCHAR(20) DEFAULT NULL,
            browser VARCHAR(50) DEFAULT NULL,
            platform VARCHAR(50) DEFAULT NULL,
            referrer VARCHAR(500) DEFAULT NULL,
            traffic_source VARCHAR(50) DEFAULT NULL,
            landing_page VARCHAR(500) DEFAULT NULL,
            page_views INT UNSIGNED DEFAULT 0,
            events_count INT UNSIGNED DEFAULT 0,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            UNIQUE KEY uk_session_id (session_id),
            KEY idx_user_id (user_id),
            KEY idx_traffic_source (traffic_source),
            KEY idx_first_seen_at (first_seen_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_analytics_sessions';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\Exclusive\Migration;

use BMN\Platform\Database\Migration;

final class CreateExclusiveListingsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_exclusive_listings';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            listing_id INT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            property_type VARCHAR(50) NOT NULL,
            property_sub_type VARCHAR(50) DEFAULT NULL,
            list_price DECIMAL(12,2) NOT NULL,
            original_list_price DECIMAL(12,2) DEFAULT NULL,
            street_number VARCHAR(50) NOT NULL,
            street_name VARCHAR(100) NOT NULL,
            unit_number VARCHAR(20) DEFAULT NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(2) NOT NULL DEFAULT 'MA',
            postal_code VARCHAR(10) NOT NULL,
            county VARCHAR(100) DEFAULT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            bedrooms_total TINYINT UNSIGNED DEFAULT NULL,
            bathrooms_total DECIMAL(3,1) DEFAULT NULL,
            bathrooms_full TINYINT UNSIGNED DEFAULT NULL,
            bathrooms_half TINYINT UNSIGNED DEFAULT NULL,
            building_area_total INT UNSIGNED DEFAULT NULL,
            lot_size_acres DECIMAL(10,4) DEFAULT NULL,
            year_built SMALLINT UNSIGNED DEFAULT NULL,
            garage_spaces TINYINT UNSIGNED DEFAULT NULL,
            stories_total TINYINT UNSIGNED DEFAULT NULL,
            has_pool TINYINT(1) DEFAULT 0,
            has_fireplace TINYINT(1) DEFAULT 0,
            has_basement TINYINT(1) DEFAULT 0,
            has_hoa TINYINT(1) DEFAULT 0,
            pet_friendly TINYINT(1) DEFAULT 0,
            architectural_style VARCHAR(100) DEFAULT NULL,
            heating VARCHAR(255) DEFAULT NULL,
            cooling VARCHAR(255) DEFAULT NULL,
            flooring VARCHAR(255) DEFAULT NULL,
            laundry_features VARCHAR(255) DEFAULT NULL,
            basement_description VARCHAR(255) DEFAULT NULL,
            interior_features TEXT DEFAULT NULL,
            appliances TEXT DEFAULT NULL,
            construction_materials VARCHAR(255) DEFAULT NULL,
            roof VARCHAR(100) DEFAULT NULL,
            foundation_details VARCHAR(255) DEFAULT NULL,
            exterior_features TEXT DEFAULT NULL,
            parking_features VARCHAR(255) DEFAULT NULL,
            parking_total TINYINT UNSIGNED DEFAULT NULL,
            waterfront_yn TINYINT(1) DEFAULT 0,
            waterfront_features VARCHAR(255) DEFAULT NULL,
            view_yn TINYINT(1) DEFAULT 0,
            view VARCHAR(255) DEFAULT NULL,
            public_remarks TEXT DEFAULT NULL,
            private_remarks TEXT DEFAULT NULL,
            showing_instructions TEXT DEFAULT NULL,
            virtual_tour_url VARCHAR(500) DEFAULT NULL,
            exclusive_tag VARCHAR(50) DEFAULT 'Exclusive',
            tax_annual_amount DECIMAL(10,2) DEFAULT NULL,
            tax_year SMALLINT UNSIGNED DEFAULT NULL,
            association_fee DECIMAL(8,2) DEFAULT NULL,
            association_fee_frequency VARCHAR(20) DEFAULT NULL,
            listing_contract_date DATE DEFAULT NULL,
            photo_count TINYINT UNSIGNED DEFAULT 0,
            main_photo_url VARCHAR(500) DEFAULT NULL,
            synced_to_properties TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_agent_user_id (agent_user_id),
            KEY idx_listing_id (listing_id),
            KEY idx_status (status),
            KEY idx_city (city),
            KEY idx_property_type (property_type),
            UNIQUE KEY uk_listing_id (listing_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_exclusive_listings';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

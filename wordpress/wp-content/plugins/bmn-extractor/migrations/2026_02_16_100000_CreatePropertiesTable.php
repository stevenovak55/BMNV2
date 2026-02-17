<?php

declare(strict_types=1);

namespace BMN\Extractor\Migrations;

use BMN\Platform\Database\Migration;

class CreatePropertiesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_properties';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_key VARCHAR(128) NOT NULL,
            listing_id VARCHAR(50) NOT NULL,
            modification_timestamp DATETIME NULL,
            creation_timestamp DATETIME NULL,
            status_change_timestamp DATETIME NULL,
            close_date DATETIME NULL,
            purchase_contract_date DATETIME NULL,
            listing_contract_date DATE NULL,
            original_entry_timestamp DATETIME NULL,
            off_market_date DATETIME NULL,
            standard_status VARCHAR(50) NULL,
            mls_status VARCHAR(50) NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            property_type VARCHAR(50) NULL,
            property_sub_type VARCHAR(50) NULL,
            list_price DECIMAL(20,2) NULL,
            original_list_price DECIMAL(20,2) NULL,
            close_price DECIMAL(20,2) NULL,
            public_remarks LONGTEXT NULL,
            showing_instructions TEXT NULL,
            main_photo_url VARCHAR(512) NULL,
            photo_count INT NOT NULL DEFAULT 0,
            virtual_tour_url_unbranded VARCHAR(255) NULL,
            virtual_tour_url_branded VARCHAR(255) NULL,
            list_agent_mls_id VARCHAR(50) NULL,
            buyer_agent_mls_id VARCHAR(50) NULL,
            list_office_mls_id VARCHAR(50) NULL,
            buyer_office_mls_id VARCHAR(50) NULL,
            bedrooms_total INT NULL,
            bathrooms_total INT NULL,
            bathrooms_full INT NULL,
            bathrooms_half INT NULL,
            living_area DECIMAL(14,2) NULL,
            above_grade_finished_area DECIMAL(14,2) NULL,
            below_grade_finished_area DECIMAL(14,2) NULL,
            building_area_total DECIMAL(14,2) NULL,
            lot_size_acres DECIMAL(20,4) NULL,
            lot_size_square_feet DECIMAL(20,2) NULL,
            year_built INT NULL,
            stories_total INT NULL,
            garage_spaces INT NULL,
            parking_total INT NULL,
            fireplaces_total INT NULL,
            rooms_total INT NULL,
            unparsed_address VARCHAR(255) NULL,
            street_number VARCHAR(50) NULL,
            street_name VARCHAR(100) NULL,
            unit_number VARCHAR(30) NULL,
            city VARCHAR(100) NULL,
            state_or_province VARCHAR(50) NULL,
            postal_code VARCHAR(20) NULL,
            county_or_parish VARCHAR(100) NULL,
            latitude DOUBLE NULL,
            longitude DOUBLE NULL,
            subdivision_name VARCHAR(100) NULL,
            elementary_school VARCHAR(100) NULL,
            middle_or_junior_school VARCHAR(100) NULL,
            high_school VARCHAR(100) NULL,
            school_district VARCHAR(100) NULL,
            tax_annual_amount DECIMAL(14,2) NULL,
            tax_year INT NULL,
            association_yn TINYINT(1) NULL,
            association_fee DECIMAL(14,2) NULL,
            association_fee_frequency VARCHAR(50) NULL,
            mls_area_major VARCHAR(100) NULL,
            mls_area_minor VARCHAR(100) NULL,
            days_on_market INT NULL,
            price_per_sqft DECIMAL(14,2) NULL,

            -- Boolean filter flags
            pool_private_yn TINYINT(1) NULL,
            waterfront_yn TINYINT(1) NULL,
            view_yn TINYINT(1) NULL,
            spa_yn TINYINT(1) NULL,
            fireplace_yn TINYINT(1) NULL,
            cooling_yn TINYINT(1) NULL,
            heating_yn TINYINT(1) NULL,
            garage_yn TINYINT(1) NULL,
            attached_garage_yn TINYINT(1) NULL,
            senior_community_yn TINYINT(1) NULL,
            horse_yn TINYINT(1) NULL,
            home_warranty_yn TINYINT(1) NULL,
            property_attached_yn TINYINT(1) NULL,

            -- Detail fields
            basement TEXT NULL,
            heating TEXT NULL,
            cooling TEXT NULL,
            construction_materials TEXT NULL,
            roof TEXT NULL,
            foundation_details TEXT NULL,
            sewer VARCHAR(100) NULL,
            water_source VARCHAR(100) NULL,
            flooring TEXT NULL,
            appliances TEXT NULL,
            laundry_features TEXT NULL,
            security_features TEXT NULL,
            interior_features TEXT NULL,
            exterior_features TEXT NULL,
            lot_features TEXT NULL,
            community_features TEXT NULL,
            patio_and_porch_features TEXT NULL,
            fencing TEXT NULL,
            pool_features TEXT NULL,
            waterfront_features TEXT NULL,
            view_description TEXT NULL,
            parking_features TEXT NULL,
            architectural_style VARCHAR(100) NULL,
            property_condition VARCHAR(100) NULL,
            accessibility_features TEXT NULL,

            -- Financial fields
            tax_assessed_value DECIMAL(14,2) NULL,
            zoning VARCHAR(50) NULL,
            parcel_number VARCHAR(50) NULL,
            gross_income DECIMAL(14,2) NULL,
            net_operating_income DECIMAL(14,2) NULL,
            total_actual_rent DECIMAL(14,2) NULL,
            number_of_units_total INT NULL,
            buyer_agency_compensation VARCHAR(50) NULL,

            -- Location fields
            street_dir_prefix VARCHAR(20) NULL,
            street_dir_suffix VARCHAR(20) NULL,
            building_name VARCHAR(100) NULL,

            -- Listing fields
            expiration_date DATE NULL,
            contingency VARCHAR(100) NULL,
            private_remarks LONGTEXT NULL,
            structure_type VARCHAR(100) NULL,

            -- JSON catch-all for complete API data
            extra_data JSON NULL,

            -- Spatial column
            coordinates POINT NOT NULL DEFAULT (ST_GeomFromText('POINT(0 0)')),

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_listing_key (listing_key),
            UNIQUE KEY uk_listing_id (listing_id),
            KEY idx_status_city_price (standard_status, city, list_price),
            KEY idx_status_type_price (standard_status, property_type, list_price),
            KEY idx_status_beds_baths (standard_status, bedrooms_total, bathrooms_total),
            KEY idx_archived_status (is_archived, standard_status),
            KEY idx_postal_code (postal_code),
            KEY idx_city_status (city, standard_status),
            KEY idx_modification (modification_timestamp),
            KEY idx_lat_lng (latitude, longitude),
            KEY idx_pool (pool_private_yn),
            KEY idx_waterfront (waterfront_yn),
            KEY idx_view (view_yn),
            KEY idx_cooling (cooling_yn),
            KEY idx_units (number_of_units_total),
            SPATIAL KEY spatial_coordinates (coordinates),
            FULLTEXT KEY ft_remarks (public_remarks)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_properties';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

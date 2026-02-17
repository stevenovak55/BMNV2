<?php

declare(strict_types=1);

namespace BMN\Extractor\Migrations;

use BMN\Platform\Database\Migration;

/**
 * Add ~55 detail columns, extra_data JSON, and spatial POINT to bmn_properties.
 *
 * For existing installations that already ran the CreatePropertiesTable migration.
 */
class AddPropertyDetailColumns extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_properties';

        // Check if columns already exist (idempotent).
        $existing = $wpdb->get_col("DESCRIBE {$table}", 0);
        if (in_array('pool_private_yn', $existing, true)) {
            return;
        }

        // Boolean filter flags.
        $wpdb->query("ALTER TABLE {$table}
            ADD COLUMN pool_private_yn TINYINT(1) NULL AFTER price_per_sqft,
            ADD COLUMN waterfront_yn TINYINT(1) NULL AFTER pool_private_yn,
            ADD COLUMN view_yn TINYINT(1) NULL AFTER waterfront_yn,
            ADD COLUMN spa_yn TINYINT(1) NULL AFTER view_yn,
            ADD COLUMN fireplace_yn TINYINT(1) NULL AFTER spa_yn,
            ADD COLUMN cooling_yn TINYINT(1) NULL AFTER fireplace_yn,
            ADD COLUMN heating_yn TINYINT(1) NULL AFTER cooling_yn,
            ADD COLUMN garage_yn TINYINT(1) NULL AFTER heating_yn,
            ADD COLUMN attached_garage_yn TINYINT(1) NULL AFTER garage_yn,
            ADD COLUMN senior_community_yn TINYINT(1) NULL AFTER attached_garage_yn,
            ADD COLUMN horse_yn TINYINT(1) NULL AFTER senior_community_yn,
            ADD COLUMN home_warranty_yn TINYINT(1) NULL AFTER horse_yn,
            ADD COLUMN property_attached_yn TINYINT(1) NULL AFTER home_warranty_yn
        ");

        // Detail fields.
        $wpdb->query("ALTER TABLE {$table}
            ADD COLUMN basement TEXT NULL AFTER property_attached_yn,
            ADD COLUMN heating TEXT NULL AFTER basement,
            ADD COLUMN cooling TEXT NULL AFTER heating,
            ADD COLUMN construction_materials TEXT NULL AFTER cooling,
            ADD COLUMN roof TEXT NULL AFTER construction_materials,
            ADD COLUMN foundation_details TEXT NULL AFTER roof,
            ADD COLUMN sewer VARCHAR(100) NULL AFTER foundation_details,
            ADD COLUMN water_source VARCHAR(100) NULL AFTER sewer,
            ADD COLUMN flooring TEXT NULL AFTER water_source,
            ADD COLUMN appliances TEXT NULL AFTER flooring,
            ADD COLUMN laundry_features TEXT NULL AFTER appliances,
            ADD COLUMN security_features TEXT NULL AFTER laundry_features,
            ADD COLUMN interior_features TEXT NULL AFTER security_features,
            ADD COLUMN exterior_features TEXT NULL AFTER interior_features,
            ADD COLUMN lot_features TEXT NULL AFTER exterior_features,
            ADD COLUMN community_features TEXT NULL AFTER lot_features,
            ADD COLUMN patio_and_porch_features TEXT NULL AFTER community_features,
            ADD COLUMN fencing TEXT NULL AFTER patio_and_porch_features,
            ADD COLUMN pool_features TEXT NULL AFTER fencing,
            ADD COLUMN waterfront_features TEXT NULL AFTER pool_features,
            ADD COLUMN view_description TEXT NULL AFTER waterfront_features,
            ADD COLUMN parking_features TEXT NULL AFTER view_description,
            ADD COLUMN architectural_style VARCHAR(100) NULL AFTER parking_features,
            ADD COLUMN property_condition VARCHAR(100) NULL AFTER architectural_style,
            ADD COLUMN accessibility_features TEXT NULL AFTER property_condition
        ");

        // Financial fields.
        $wpdb->query("ALTER TABLE {$table}
            ADD COLUMN tax_assessed_value DECIMAL(14,2) NULL AFTER accessibility_features,
            ADD COLUMN zoning VARCHAR(50) NULL AFTER tax_assessed_value,
            ADD COLUMN parcel_number VARCHAR(50) NULL AFTER zoning,
            ADD COLUMN gross_income DECIMAL(14,2) NULL AFTER parcel_number,
            ADD COLUMN net_operating_income DECIMAL(14,2) NULL AFTER gross_income,
            ADD COLUMN total_actual_rent DECIMAL(14,2) NULL AFTER net_operating_income,
            ADD COLUMN number_of_units_total INT NULL AFTER total_actual_rent,
            ADD COLUMN buyer_agency_compensation VARCHAR(50) NULL AFTER number_of_units_total
        ");

        // Location fields.
        $wpdb->query("ALTER TABLE {$table}
            ADD COLUMN street_dir_prefix VARCHAR(20) NULL AFTER buyer_agency_compensation,
            ADD COLUMN street_dir_suffix VARCHAR(20) NULL AFTER street_dir_prefix,
            ADD COLUMN building_name VARCHAR(100) NULL AFTER street_dir_suffix
        ");

        // Listing fields.
        $wpdb->query("ALTER TABLE {$table}
            ADD COLUMN expiration_date DATE NULL AFTER building_name,
            ADD COLUMN contingency VARCHAR(100) NULL AFTER expiration_date,
            ADD COLUMN private_remarks LONGTEXT NULL AFTER contingency,
            ADD COLUMN structure_type VARCHAR(100) NULL AFTER private_remarks
        ");

        // JSON catch-all.
        $wpdb->query("ALTER TABLE {$table}
            ADD COLUMN extra_data JSON NULL AFTER structure_type
        ");

        // Spatial POINT column — add as NULL first, backfill, then set NOT NULL.
        $wpdb->query("ALTER TABLE {$table}
            ADD COLUMN coordinates POINT NULL AFTER extra_data
        ");

        // Backfill coordinates from existing lat/lng.
        $wpdb->query("UPDATE {$table} SET coordinates = ST_GeomFromText(
            CONCAT('POINT(', COALESCE(longitude, 0), ' ', COALESCE(latitude, 0), ')')
        )");

        // Set NOT NULL with default.
        $wpdb->query("ALTER TABLE {$table}
            MODIFY COLUMN coordinates POINT NOT NULL DEFAULT (ST_GeomFromText('POINT(0 0)'))
        ");

        // Add indexes.
        $wpdb->query("ALTER TABLE {$table}
            ADD KEY idx_pool (pool_private_yn),
            ADD KEY idx_waterfront (waterfront_yn),
            ADD KEY idx_view (view_yn),
            ADD KEY idx_cooling (cooling_yn),
            ADD KEY idx_units (number_of_units_total),
            ADD SPATIAL KEY spatial_coordinates (coordinates)
        ");
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_properties';

        // Drop spatial index first.
        $wpdb->query("ALTER TABLE {$table} DROP KEY spatial_coordinates");
        $wpdb->query("ALTER TABLE {$table} DROP KEY idx_pool, DROP KEY idx_waterfront, DROP KEY idx_view, DROP KEY idx_cooling, DROP KEY idx_units");

        // Drop columns in reverse order.
        $columns = [
            'coordinates', 'extra_data',
            'structure_type', 'private_remarks', 'contingency', 'expiration_date',
            'building_name', 'street_dir_suffix', 'street_dir_prefix',
            'buyer_agency_compensation', 'number_of_units_total', 'total_actual_rent',
            'net_operating_income', 'gross_income', 'parcel_number', 'zoning', 'tax_assessed_value',
            'accessibility_features', 'property_condition', 'architectural_style',
            'parking_features', 'view_description', 'waterfront_features', 'pool_features',
            'fencing', 'patio_and_porch_features', 'community_features', 'lot_features',
            'exterior_features', 'interior_features', 'security_features', 'laundry_features',
            'appliances', 'flooring', 'water_source', 'sewer', 'foundation_details',
            'roof', 'construction_materials', 'cooling', 'heating', 'basement',
            'property_attached_yn', 'home_warranty_yn', 'horse_yn', 'senior_community_yn',
            'attached_garage_yn', 'garage_yn', 'heating_yn', 'cooling_yn',
            'fireplace_yn', 'spa_yn', 'view_yn', 'waterfront_yn', 'pool_private_yn',
        ];

        $dropList = implode(', ', array_map(fn($c) => "DROP COLUMN {$c}", $columns));
        $wpdb->query("ALTER TABLE {$table} {$dropList}");
    }
}

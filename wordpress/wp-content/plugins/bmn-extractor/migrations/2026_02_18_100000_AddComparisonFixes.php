<?php

declare(strict_types=1);

namespace BMN\Extractor\Migrations;

use BMN\Platform\Database\Migration;

/**
 * Session 24 comparison fixes: new columns, indexes, SRID fix, index cleanup.
 *
 * Fix 1: MA compliance columns (lead_paint, title5, disclosures)
 * Fix 2: Pet detail columns (pets_dogs_allowed, pets_cats_allowed)
 * Fix 4: Archive sort index (is_archived, standard_status, close_date)
 * Fix 5: Active sort index (is_archived, standard_status, listing_contract_date)
 * Fix 6: Spatial SRID fix (coordinates stored with SRID 0 → re-store as SRID 4326)
 * Fix 10: Drop low-value boolean indexes (idx_pool, idx_waterfront, idx_view, idx_cooling)
 */
class AddComparisonFixes extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_properties';

        // Check if columns already exist (idempotent).
        $existing = $wpdb->get_col("DESCRIBE {$table}", 0);

        // Fix 1: MA compliance columns.
        if (!in_array('lead_paint', $existing, true)) {
            $wpdb->query("ALTER TABLE {$table}
                ADD COLUMN lead_paint TINYINT(1) NULL AFTER structure_type,
                ADD COLUMN title5 VARCHAR(10) NULL AFTER lead_paint,
                ADD COLUMN disclosures LONGTEXT NULL AFTER title5
            ");
        }

        // Fix 2: Pet detail columns.
        if (!in_array('pets_dogs_allowed', $existing, true)) {
            $wpdb->query("ALTER TABLE {$table}
                ADD COLUMN pets_dogs_allowed TINYINT(1) NULL AFTER property_attached_yn,
                ADD COLUMN pets_cats_allowed TINYINT(1) NULL AFTER pets_dogs_allowed
            ");
        }

        // Fix 4: Archive sort index.
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $indexNames = array_unique(array_column($indexes, 'Key_name'));

        if (!in_array('idx_archived_status_close', $indexNames, true)) {
            $wpdb->query("ALTER TABLE {$table}
                ADD KEY idx_archived_status_close (is_archived, standard_status, close_date)
            ");
        }

        // Fix 5: Active sort index.
        if (!in_array('idx_archived_status_listdate', $indexNames, true)) {
            $wpdb->query("ALTER TABLE {$table}
                ADD KEY idx_archived_status_listdate (is_archived, standard_status, listing_contract_date)
            ");
        }

        // Fix 6: Spatial SRID fix — re-store coordinates with SRID 4326.
        // Step 1: Drop the spatial index.
        if (in_array('spatial_coordinates', $indexNames, true)) {
            $wpdb->query("ALTER TABLE {$table} DROP KEY spatial_coordinates");
        }

        // Step 2: Add a temporary column with SRID 4326.
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN coordinates_4326 POINT SRID 4326 NULL AFTER coordinates");

        // Step 3: Backfill from lat/lng (more reliable than converting existing POINT with SRID 0).
        $wpdb->query("UPDATE {$table} SET coordinates_4326 = ST_GeomFromText(
            CONCAT('POINT(', COALESCE(longitude, 0), ' ', COALESCE(latitude, 0), ')'), 4326
        )");

        // Step 4: Drop old column, rename new.
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN coordinates");
        $wpdb->query("ALTER TABLE {$table} CHANGE coordinates_4326 coordinates POINT NOT NULL SRID 4326 DEFAULT (ST_GeomFromText('POINT(0 0)', 4326))");

        // Step 5: Recreate spatial index.
        $wpdb->query("ALTER TABLE {$table} ADD SPATIAL KEY spatial_coordinates (coordinates)");

        // Fix 10: Drop low-value boolean indexes.
        // Re-fetch indexes after previous changes.
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $indexNames = array_unique(array_column($indexes, 'Key_name'));

        $dropIndexes = ['idx_pool', 'idx_waterfront', 'idx_view', 'idx_cooling'];
        foreach ($dropIndexes as $idx) {
            if (in_array($idx, $indexNames, true)) {
                $wpdb->query("ALTER TABLE {$table} DROP KEY {$idx}");
            }
        }
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_properties';

        // Reverse Fix 10: Restore boolean indexes.
        $wpdb->query("ALTER TABLE {$table}
            ADD KEY idx_pool (pool_private_yn),
            ADD KEY idx_waterfront (waterfront_yn),
            ADD KEY idx_view (view_yn),
            ADD KEY idx_cooling (cooling_yn)
        ");

        // Reverse Fix 6: Revert SRID to 0.
        $wpdb->query("ALTER TABLE {$table} DROP KEY spatial_coordinates");
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN coordinates_0 POINT NOT NULL DEFAULT (ST_GeomFromText('POINT(0 0)')) AFTER coordinates");
        $wpdb->query("UPDATE {$table} SET coordinates_0 = ST_GeomFromText(
            CONCAT('POINT(', COALESCE(longitude, 0), ' ', COALESCE(latitude, 0), ')')
        )");
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN coordinates");
        $wpdb->query("ALTER TABLE {$table} CHANGE coordinates_0 coordinates POINT NOT NULL DEFAULT (ST_GeomFromText('POINT(0 0)'))");
        $wpdb->query("ALTER TABLE {$table} ADD SPATIAL KEY spatial_coordinates (coordinates)");

        // Reverse Fix 5 & 4.
        $wpdb->query("ALTER TABLE {$table} DROP KEY idx_archived_status_listdate");
        $wpdb->query("ALTER TABLE {$table} DROP KEY idx_archived_status_close");

        // Reverse Fix 2.
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN pets_cats_allowed, DROP COLUMN pets_dogs_allowed");

        // Reverse Fix 1.
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN disclosures, DROP COLUMN title5, DROP COLUMN lead_paint");
    }
}

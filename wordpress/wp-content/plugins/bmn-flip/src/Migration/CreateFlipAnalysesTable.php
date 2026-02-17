<?php

declare(strict_types=1);

namespace BMN\Flip\Migration;

use BMN\Platform\Database\Migration;

final class CreateFlipAnalysesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_analyses';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED DEFAULT NULL,
            listing_id VARCHAR(50) NOT NULL,
            address VARCHAR(500) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            state VARCHAR(2) DEFAULT 'MA',
            zip VARCHAR(10) DEFAULT NULL,
            list_price DECIMAL(12,2) NOT NULL,
            property_type VARCHAR(50) DEFAULT NULL,
            bedrooms_total INT UNSIGNED DEFAULT NULL,
            bathrooms_total DECIMAL(4,1) DEFAULT NULL,
            living_area INT UNSIGNED DEFAULT NULL,
            lot_size_acres DECIMAL(8,4) DEFAULT NULL,
            year_built INT UNSIGNED DEFAULT NULL,
            garage_spaces INT UNSIGNED DEFAULT 0,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            days_on_market INT UNSIGNED DEFAULT NULL,
            original_list_price DECIMAL(12,2) DEFAULT NULL,
            estimated_arv DECIMAL(12,2) DEFAULT NULL,
            arv_confidence VARCHAR(20) DEFAULT NULL,
            arv_confidence_score DECIMAL(5,1) DEFAULT NULL,
            comp_count INT UNSIGNED DEFAULT 0,
            avg_comp_ppsf DECIMAL(8,2) DEFAULT NULL,
            neighborhood_ceiling DECIMAL(12,2) DEFAULT NULL,
            estimated_rehab_cost DECIMAL(12,2) DEFAULT NULL,
            rehab_per_sqft DECIMAL(8,2) DEFAULT NULL,
            estimated_hold_months INT UNSIGNED DEFAULT NULL,
            purchase_closing_cost DECIMAL(10,2) DEFAULT NULL,
            sale_costs DECIMAL(10,2) DEFAULT NULL,
            holding_costs DECIMAL(10,2) DEFAULT NULL,
            cash_profit DECIMAL(12,2) DEFAULT NULL,
            cash_roi DECIMAL(8,2) DEFAULT NULL,
            cash_investment DECIMAL(12,2) DEFAULT NULL,
            financed_profit DECIMAL(12,2) DEFAULT NULL,
            cash_on_cash_roi DECIMAL(8,2) DEFAULT NULL,
            annualized_roi DECIMAL(8,2) DEFAULT NULL,
            mao_classic DECIMAL(12,2) DEFAULT NULL,
            mao_adjusted DECIMAL(12,2) DEFAULT NULL,
            breakeven_arv DECIMAL(12,2) DEFAULT NULL,
            total_score DECIMAL(5,1) DEFAULT NULL,
            financial_score DECIMAL(5,1) DEFAULT NULL,
            property_score DECIMAL(5,1) DEFAULT NULL,
            location_score DECIMAL(5,1) DEFAULT NULL,
            market_score DECIMAL(5,1) DEFAULT NULL,
            flip_score DECIMAL(5,1) DEFAULT NULL,
            rental_score DECIMAL(5,1) DEFAULT NULL,
            brrrr_score DECIMAL(5,1) DEFAULT NULL,
            best_strategy VARCHAR(20) DEFAULT NULL,
            flip_viable TINYINT(1) DEFAULT 0,
            rental_viable TINYINT(1) DEFAULT 0,
            brrrr_viable TINYINT(1) DEFAULT 0,
            disqualified TINYINT(1) DEFAULT 0,
            dq_reason VARCHAR(255) DEFAULT NULL,
            deal_risk_grade VARCHAR(2) DEFAULT NULL,
            market_strength VARCHAR(20) DEFAULT NULL,
            rental_analysis JSON DEFAULT NULL,
            remarks_signals JSON DEFAULT NULL,
            applied_thresholds JSON DEFAULT NULL,
            run_date DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_listing_id (listing_id),
            KEY idx_report_id (report_id),
            KEY idx_city (city),
            KEY idx_total_score (total_score),
            KEY idx_run_date (run_date),
            KEY idx_disqualified (disqualified),
            KEY idx_best_strategy (best_strategy)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_flip_analyses';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

<?php

declare(strict_types=1);

namespace BMN\CMA\Service;

use BMN\CMA\Repository\CmaReportRepository;
use BMN\CMA\Repository\ComparableRepository;
use BMN\CMA\Repository\ValueHistoryRepository;
use RuntimeException;

/**
 * Orchestrates CMA report generation, retrieval, and management.
 */
class CmaReportService
{
    private readonly CmaReportRepository $reportRepo;
    private readonly ComparableRepository $comparableRepo;
    private readonly ValueHistoryRepository $historyRepo;
    private readonly ComparableSearchService $searchService;
    private readonly AdjustmentService $adjustmentService;

    public function __construct(
        CmaReportRepository $reportRepo,
        ComparableRepository $comparableRepo,
        ValueHistoryRepository $historyRepo,
        ComparableSearchService $searchService,
        AdjustmentService $adjustmentService,
    ) {
        $this->reportRepo = $reportRepo;
        $this->comparableRepo = $comparableRepo;
        $this->historyRepo = $historyRepo;
        $this->searchService = $searchService;
        $this->adjustmentService = $adjustmentService;
    }

    /**
     * Generate a full CMA report: find comps, adjust, score, save.
     *
     * @param array $subjectData Subject property data (must have latitude, longitude, listing_id)
     * @param array $filters Search filter overrides
     * @param int $userId Authenticated user ID
     * @return array Full report data including comparables and valuation
     */
    public function generateReport(array $subjectData, array $filters, int $userId): array
    {
        // Build subject object for adjustment calculations.
        $subject = (object) $subjectData;

        // Find comparables.
        $rawComps = $this->searchService->findComparables($subjectData, $filters);

        // Expand search if insufficient results.
        if (count($rawComps) < ($filters['min_comps'] ?? 5)) {
            $rawComps = $this->searchService->expandSearch($subjectData, $filters, count($rawComps));
        }

        // Calculate adjustments for each comparable.
        $processedComps = [];
        $adjustedPrices = [];

        foreach ($rawComps as $comp) {
            $adjResult = $this->adjustmentService->calculateAdjustments($subject, $comp);
            $grade = $this->adjustmentService->gradeComparable($adjResult['gross_pct']);

            $comp->adjustments = $adjResult['adjustments'];
            $comp->adjustment_total = $adjResult['total'];
            $comp->adjusted_price = $adjResult['adjusted_price'];
            $comp->gross_pct = $adjResult['gross_pct'];
            $comp->comparability_grade = $grade;

            // Calculate comparability score (inverse of gross adjustment pct, 0-100).
            $comp->comparability_score = round(max(0, 100 - ($adjResult['gross_pct'] * 2.5)), 2);

            $processedComps[] = $comp;
            if ($adjResult['adjusted_price'] > 0) {
                $adjustedPrices[] = $adjResult['adjusted_price'];
            }
        }

        // Calculate confidence.
        $confidence = $this->adjustmentService->calculateConfidence($processedComps, $subject);

        // Calculate valuation range.
        $valuation = $this->adjustmentService->calculateValuation($adjustedPrices);

        // Calculate summary statistics.
        $summaryStats = $this->buildSummaryStatistics($processedComps, $adjustedPrices);

        // Save the report.
        $now = current_time('mysql');
        $reportId = $this->reportRepo->create([
            'user_id'              => $userId,
            'session_name'         => $subjectData['session_name'] ?? null,
            'subject_listing_id'   => $subjectData['listing_id'] ?? null,
            'subject_address'      => $subjectData['address'] ?? null,
            'subject_city'         => $subjectData['city'] ?? null,
            'subject_state'        => $subjectData['state'] ?? 'MA',
            'subject_zip'          => $subjectData['zip'] ?? null,
            'subject_data'         => $subjectData,
            'subject_overrides'    => $subjectData['overrides'] ?? [],
            'cma_filters'          => $filters,
            'comparables_count'    => count($processedComps),
            'estimated_value_low'  => $valuation['low'],
            'estimated_value_mid'  => $valuation['mid'],
            'estimated_value_high' => $valuation['high'],
            'confidence_score'     => $confidence['score'],
            'confidence_level'     => $confidence['level'],
            'summary_statistics'   => $summaryStats,
            'is_arv_mode'         => (int) ($subjectData['is_arv_mode'] ?? 0),
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        if ($reportId === false) {
            throw new RuntimeException('Failed to create CMA report.');
        }

        // Save each comparable.
        foreach ($processedComps as $comp) {
            $this->comparableRepo->upsert([
                'report_id'           => $reportId,
                'listing_id'          => $comp->listing_id ?? '',
                'close_price'         => (float) ($comp->close_price ?? 0),
                'adjusted_price'      => $comp->adjusted_price,
                'adjustment_total'    => $comp->adjustment_total,
                'adjustments'         => $comp->adjustments,
                'comparability_score' => $comp->comparability_score,
                'comparability_grade' => $comp->comparability_grade,
                'distance_miles'      => (float) ($comp->distance_miles ?? 0),
                'property_data'       => $this->extractPropertyData($comp),
                'is_selected'         => 1,
            ]);
        }

        // Save value history entry.
        $avgPricePerSqft = 0.0;
        if (!empty($subjectData['living_area']) && count($adjustedPrices) > 0) {
            $avgPricePerSqft = (array_sum($adjustedPrices) / count($adjustedPrices)) / (int) $subjectData['living_area'];
        }

        $this->historyRepo->create([
            'listing_id'           => $subjectData['listing_id'] ?? null,
            'property_address'     => $subjectData['address'] ?? null,
            'report_id'            => $reportId,
            'user_id'              => $userId,
            'estimated_value_low'  => $valuation['low'],
            'estimated_value_mid'  => $valuation['mid'],
            'estimated_value_high' => $valuation['high'],
            'comparables_count'    => count($processedComps),
            'confidence_score'     => $confidence['score'],
            'confidence_level'     => $confidence['level'],
            'avg_price_per_sqft'   => round($avgPricePerSqft, 2),
            'is_arv_mode'         => (int) ($subjectData['is_arv_mode'] ?? 0),
        ]);

        return [
            'report_id'   => $reportId,
            'subject'     => $subjectData,
            'comparables' => $this->formatComparables($processedComps),
            'valuation'   => $valuation,
            'confidence'  => $confidence,
            'statistics'  => $summaryStats,
        ];
    }

    /**
     * Get a single report with its comparables (user ownership verified).
     */
    public function getReport(int $reportId, int $userId): ?array
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null || (int) $report->user_id !== $userId) {
            return null;
        }

        $comparables = $this->comparableRepo->findByReport($reportId);

        return [
            'report'      => $report,
            'comparables' => $comparables,
        ];
    }

    /**
     * Get paginated list of user's reports.
     *
     * @return array{reports: object[], total: int}
     */
    public function getUserReports(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $reports = $this->reportRepo->findByUser($userId, $perPage, $offset);
        $total = $this->reportRepo->countByUser($userId);

        return [
            'reports' => $reports,
            'total'   => $total,
        ];
    }

    /**
     * Update a report (user ownership verified).
     */
    public function updateReport(int $reportId, int $userId, array $data): bool
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null || (int) $report->user_id !== $userId) {
            return false;
        }

        return $this->reportRepo->update($reportId, $data);
    }

    /**
     * Delete a report and its comparables (user ownership verified).
     */
    public function deleteReport(int $reportId, int $userId): bool
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null || (int) $report->user_id !== $userId) {
            return false;
        }

        $this->comparableRepo->deleteByReport($reportId);

        return $this->reportRepo->delete($reportId);
    }

    /**
     * Toggle favorite status on a report (user ownership verified).
     */
    public function toggleFavorite(int $reportId, int $userId): bool
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null || (int) $report->user_id !== $userId) {
            return false;
        }

        return $this->reportRepo->toggleFavorite($reportId);
    }

    /**
     * Get value history for a property.
     *
     * @return object[]
     */
    public function getPropertyHistory(string $listingId): array
    {
        return $this->historyRepo->findByListing($listingId);
    }

    /**
     * Get chronological value trend data for charting.
     *
     * @return object[]
     */
    public function getValueTrends(string $listingId): array
    {
        return $this->historyRepo->getTrends($listingId);
    }

    /**
     * Build summary statistics from processed comparables.
     */
    private function buildSummaryStatistics(array $comparables, array $adjustedPrices): array
    {
        $count = count($comparables);

        if ($count === 0) {
            return [
                'total_comparables' => 0,
                'avg_close_price'   => 0,
                'avg_adjusted_price' => 0,
                'avg_distance'      => 0,
                'avg_dom'           => 0,
                'grade_distribution' => [],
            ];
        }

        $closePrices = array_map(
            static fn (object $c): float => (float) ($c->close_price ?? 0),
            $comparables
        );

        $distances = array_map(
            static fn (object $c): float => (float) ($c->distance_miles ?? 0),
            $comparables
        );

        $doms = array_filter(array_map(
            static fn (object $c): int => (int) ($c->days_on_market ?? 0),
            $comparables
        ), static fn (int $d): bool => $d > 0);

        $grades = array_count_values(array_map(
            static fn (object $c): string => (string) ($c->comparability_grade ?? 'F'),
            $comparables
        ));

        return [
            'total_comparables'  => $count,
            'avg_close_price'    => round(array_sum($closePrices) / $count, 2),
            'avg_adjusted_price' => count($adjustedPrices) > 0
                ? round(array_sum($adjustedPrices) / count($adjustedPrices), 2) : 0,
            'avg_distance'       => round(array_sum($distances) / $count, 2),
            'avg_dom'            => count($doms) > 0 ? (int) round(array_sum($doms) / count($doms)) : 0,
            'grade_distribution' => $grades,
        ];
    }

    /**
     * Extract standard property fields from a comparable for storage.
     */
    private function extractPropertyData(object $comp): array
    {
        return [
            'address'          => $comp->address ?? null,
            'city'             => $comp->city ?? null,
            'state'            => $comp->state ?? null,
            'zip'              => $comp->zip ?? null,
            'property_type'    => $comp->property_type ?? null,
            'bedrooms_total'   => $comp->bedrooms_total ?? null,
            'bathrooms_total'  => $comp->bathrooms_total ?? null,
            'living_area'      => $comp->living_area ?? null,
            'year_built'       => $comp->year_built ?? null,
            'lot_size_acres'   => $comp->lot_size_acres ?? null,
            'garage_spaces'    => $comp->garage_spaces ?? null,
            'close_date'       => $comp->close_date ?? null,
            'days_on_market'   => $comp->days_on_market ?? null,
            'latitude'         => $comp->latitude ?? null,
            'longitude'        => $comp->longitude ?? null,
        ];
    }

    /**
     * Format comparables for API response.
     */
    private function formatComparables(array $comparables): array
    {
        return array_map(static fn (object $comp): array => [
            'listing_id'          => $comp->listing_id ?? '',
            'address'             => $comp->address ?? null,
            'city'                => $comp->city ?? null,
            'close_price'         => (float) ($comp->close_price ?? 0),
            'adjusted_price'      => $comp->adjusted_price ?? 0,
            'adjustment_total'    => $comp->adjustment_total ?? 0,
            'adjustments'         => $comp->adjustments ?? [],
            'comparability_score' => $comp->comparability_score ?? 0,
            'comparability_grade' => $comp->comparability_grade ?? 'F',
            'distance_miles'      => (float) ($comp->distance_miles ?? 0),
            'gross_pct'           => $comp->gross_pct ?? 0,
            'property_type'       => $comp->property_type ?? null,
            'bedrooms_total'      => $comp->bedrooms_total ?? null,
            'bathrooms_total'     => $comp->bathrooms_total ?? null,
            'living_area'         => $comp->living_area ?? null,
            'year_built'          => $comp->year_built ?? null,
            'close_date'          => $comp->close_date ?? null,
        ], $comparables);
    }
}

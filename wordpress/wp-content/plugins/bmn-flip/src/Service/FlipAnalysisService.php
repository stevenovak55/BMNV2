<?php

declare(strict_types=1);

namespace BMN\Flip\Service;

use BMN\Flip\Repository\FlipAnalysisRepository;
use BMN\Flip\Repository\FlipComparableRepository;

/**
 * Orchestrates the full flip analysis pipeline: fetches property data,
 * runs ARV, financials, scoring, disqualification, and stores results.
 */
class FlipAnalysisService
{
    public function __construct(
        private readonly ArvService $arvService,
        private readonly FinancialService $financialService,
        private readonly FlipAnalysisRepository $analysisRepo,
        private readonly FlipComparableRepository $comparableRepo,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Run the full analysis pipeline for a single property.
     *
     * @param array    $propertyData Property data as associative array.
     * @param int|null $reportId     Optional parent report ID.
     *
     * @return array Complete analysis result.
     */
    public function analyzeProperty(array $propertyData, ?int $reportId = null): array
    {
        // 1. ARV.
        $arvResult = $this->arvService->calculateArv($propertyData);

        // 2. Rehab.
        $rehabResult = $this->financialService->estimateRehabCost($propertyData);

        // 3. Hold period.
        $avgDom     = (int) ($propertyData['days_on_market'] ?? 30);
        $holdMonths = $this->financialService->estimateHoldPeriod(
            $rehabResult['per_sqft'],
            $avgDom
        );

        // 4. Transaction costs.
        $listPrice       = (float) ($propertyData['list_price'] ?? 0);
        $arv             = $arvResult['arv'];
        $transactionCosts = $this->financialService->calculateTransactionCosts($listPrice, $arv);

        // 5. Holding costs.
        $taxRate      = (float) ($propertyData['tax_rate'] ?? 0);
        $holdingCosts = $this->financialService->calculateHoldingCosts($listPrice, $holdMonths, $taxRate);

        // 6. Cash scenario.
        $cashScenario = $this->financialService->calculateCashScenario(
            $listPrice,
            $arv,
            $rehabResult['total'],
            $transactionCosts['purchase_closing'],
            $transactionCosts['sale_costs'],
            $holdingCosts['total']
        );

        // 7. Financed scenario.
        $financedScenario = $this->financialService->calculateFinancedScenario(
            $listPrice,
            $arv,
            $rehabResult['total'],
            $transactionCosts['purchase_closing'],
            $transactionCosts['sale_costs'],
            $holdingCosts['total'],
            $holdMonths
        );

        // 8. MAO.
        $mao = $this->financialService->calculateMao(
            $arv,
            $rehabResult['total'],
            $holdingCosts['total'],
            $financedScenario['financing_costs']
        );

        // 9. Breakeven ARV.
        $breakevenArv = $this->financialService->calculateBreakevenArv(
            $listPrice,
            $rehabResult['total'],
            $transactionCosts['purchase_closing'],
            $holdingCosts['total'],
            $financedScenario['financing_costs']
        );

        // 10. Disqualification check.
        $dqReason = $this->checkDisqualification($propertyData, $arvResult, $rehabResult);

        // 11. Rental analysis.
        $monthlyRent = $this->financialService->estimateMonthlyRent($propertyData);
        $rental      = $this->financialService->calculateRentalAnalysis(
            $arv,
            $monthlyRent,
            $cashScenario['investment'],
            $taxRate
        );

        // 12. BRRRR.
        $brrrr = $this->financialService->calculateBrrrr(
            $arv,
            $rental['noi'],
            $cashScenario['investment']
        );

        // 13. Viability.
        $viability = $this->checkViability(
            [
                'cash_profit'      => $cashScenario['profit'],
                'cash_roi'         => $cashScenario['roi'],
                'cash_investment'  => $cashScenario['investment'],
            ],
            $arvResult,
            $rental,
            $brrrr,
            $dqReason
        );

        // 14. Composite score.
        $scores = $this->calculateCompositeScore(
            [
                'list_price'           => $listPrice,
                'arv'                  => $arv,
                'cash_roi'             => $cashScenario['roi'],
                'original_list_price'  => (float) ($propertyData['original_list_price'] ?? $listPrice),
                'days_on_market'       => $avgDom,
            ],
            $propertyData,
            $arvResult
        );

        // 15. Risk grade.
        $priceVarianceCv = $this->computePriceVarianceCv($arvResult['comparables']);
        $riskGrade       = $this->financialService->gradeRisk(
            $arvResult['confidence_score'],
            $breakevenArv,
            $arv,
            $priceVarianceCv,
            $avgDom,
            $arvResult['comp_count']
        );

        // Build the full result.
        $result = [
            'listing_id'           => (string) ($propertyData['listing_id'] ?? ''),
            'address'              => (string) ($propertyData['address'] ?? ''),
            'city'                 => (string) ($propertyData['city'] ?? ''),
            'state'                => (string) ($propertyData['state'] ?? 'MA'),
            'zip'                  => (string) ($propertyData['zip'] ?? ''),
            'list_price'           => $listPrice,
            'property_type'        => (string) ($propertyData['property_type'] ?? ''),
            'bedrooms_total'       => (int) ($propertyData['bedrooms_total'] ?? 0),
            'bathrooms_total'      => (float) ($propertyData['bathrooms_total'] ?? 0),
            'living_area'          => (int) ($propertyData['living_area'] ?? 0),
            'lot_size_acres'       => (float) ($propertyData['lot_size_acres'] ?? 0),
            'year_built'           => (int) ($propertyData['year_built'] ?? 0),
            'garage_spaces'        => (int) ($propertyData['garage_spaces'] ?? 0),
            'latitude'             => (float) ($propertyData['latitude'] ?? 0),
            'longitude'            => (float) ($propertyData['longitude'] ?? 0),
            'days_on_market'       => $avgDom,
            'original_list_price'  => (float) ($propertyData['original_list_price'] ?? $listPrice),

            // ARV.
            'estimated_arv'        => $arv,
            'arv_confidence'       => $arvResult['confidence'],
            'arv_confidence_score' => $arvResult['confidence_score'],
            'comp_count'           => $arvResult['comp_count'],
            'avg_comp_ppsf'        => $arvResult['avg_ppsf'],
            'neighborhood_ceiling' => $arvResult['neighborhood_ceiling'],

            // Rehab.
            'estimated_rehab_cost' => $rehabResult['total'],
            'rehab_per_sqft'       => $rehabResult['per_sqft'],
            'estimated_hold_months' => $holdMonths,

            // Transaction/holding.
            'purchase_closing_cost' => $transactionCosts['purchase_closing'],
            'sale_costs'            => $transactionCosts['sale_costs'],
            'holding_costs'         => $holdingCosts['total'],

            // Cash scenario.
            'cash_profit'          => $cashScenario['profit'],
            'cash_roi'             => $cashScenario['roi'],
            'cash_investment'      => $cashScenario['investment'],

            // Financed scenario.
            'financed_profit'      => $financedScenario['profit'],
            'cash_on_cash_roi'     => $financedScenario['cash_on_cash_roi'],
            'annualized_roi'       => $financedScenario['annualized_roi'],

            // MAO.
            'mao_classic'          => $mao['classic'],
            'mao_adjusted'         => $mao['adjusted'],
            'breakeven_arv'        => $breakevenArv,

            // Scores.
            'total_score'          => $scores['total_score'],
            'financial_score'      => $scores['financial_score'],
            'property_score'       => $scores['property_score'],
            'location_score'       => $scores['location_score'],
            'market_score'         => $scores['market_score'],
            'flip_score'           => $scores['flip_score'],
            'rental_score'         => $scores['rental_score'],
            'brrrr_score'          => $scores['brrrr_score'],

            // Viability.
            'best_strategy'        => $viability['best_strategy'],
            'flip_viable'          => $viability['flip_viable'],
            'rental_viable'        => $viability['rental_viable'],
            'brrrr_viable'         => $viability['brrrr_viable'],
            'disqualified'         => $viability['disqualified'],
            'dq_reason'            => $dqReason,
            'deal_risk_grade'      => $riskGrade['grade'],

            // Detailed sub-results.
            'rental_analysis'      => $rental,
            'brrrr_analysis'       => $brrrr,
            'risk_factors'         => $riskGrade['factors'],
            'comparables'          => $arvResult['comparables'],
        ];

        // 16. Save analysis to DB.
        $analysisId = $this->saveAnalysis($result, $reportId);

        // 17. Save comps to DB.
        if ($analysisId !== false && $analysisId > 0) {
            $this->saveComparables($analysisId, $arvResult['comparables']);
            $result['id'] = $analysisId;
        }

        $result['report_id'] = $reportId;

        return $result;
    }

    /**
     * Get a single analysis with its comparables.
     */
    public function getAnalysis(int $analysisId): ?array
    {
        $analysis = $this->analysisRepo->find($analysisId);

        if ($analysis === null) {
            return null;
        }

        $comps = $this->comparableRepo->findByAnalysis($analysisId);

        $result              = (array) $analysis;
        $result['comparables'] = $comps;

        return $result;
    }

    /**
     * Get paginated analyses for a report.
     *
     * @return array{analyses: array, total: int}
     */
    public function getAnalysesByReport(int $reportId, int $page = 1, int $perPage = 50): array
    {
        $offset   = ($page - 1) * $perPage;
        $analyses = $this->analysisRepo->findByReport($reportId, $perPage, $offset);
        $total    = $this->analysisRepo->countByReport($reportId);

        return [
            'analyses' => $analyses,
            'total'    => $total,
        ];
    }

    /**
     * Get per-city summary for a report.
     */
    public function getReportSummary(int $reportId): array
    {
        return $this->analysisRepo->getReportSummary($reportId) ?? [];
    }

    /**
     * Delete all analyses (and their comps) for a report.
     */
    public function deleteAnalysesByReport(int $reportId): bool
    {
        // Delete comps first (foreign key safety).
        $this->comparableRepo->deleteByReport($reportId);

        return $this->analysisRepo->deleteByReport($reportId);
    }

    // ------------------------------------------------------------------
    // Private methods
    // ------------------------------------------------------------------

    /**
     * Check for universal disqualification conditions.
     *
     * @return string|null Reason string, or null if not disqualified.
     */
    private function checkDisqualification(array $property, array $arvResult, array $rehabResult): ?string
    {
        $listPrice  = (float) ($property['list_price'] ?? 0);
        $livingArea = (int) ($property['living_area'] ?? 0);
        $compCount  = $arvResult['comp_count'];

        if ($listPrice < 100000) {
            return 'List price below $100K minimum';
        }

        if ($compCount === 0) {
            return 'No comparable sales found';
        }

        if ($livingArea < 600) {
            return 'Living area below 600 sqft minimum';
        }

        return null;
    }

    /**
     * Determine viability for each investment strategy.
     *
     * @return array{flip_viable: bool, rental_viable: bool, brrrr_viable: bool,
     *               disqualified: bool, best_strategy: string|null}
     */
    private function checkViability(
        array $financials,
        array $arvResult,
        array $rental,
        array $brrrr,
        ?string $dqReason,
    ): array {
        $hasUniversalDq = $dqReason !== null;

        // Flip: no DQ, cash profit > $25K, cash ROI > 15%.
        $flipViable = !$hasUniversalDq
            && ($financials['cash_profit'] ?? 0) > 25000
            && ($financials['cash_roi'] ?? 0) > 15;

        // Rental: no DQ, cap rate >= 3.0%, monthly NOI > -$200.
        $rentalViable = !$hasUniversalDq
            && ($rental['cap_rate'] ?? 0) >= 3.0
            && (($rental['noi'] ?? 0) / 12) > -200;

        // BRRRR: no DQ, DSCR >= 0.9, cash left < 2x total cash in.
        $totalCashIn  = $financials['cash_investment'] ?? 0;
        $brrrrViable  = !$hasUniversalDq
            && ($brrrr['dscr'] ?? 0) >= 0.9
            && ($brrrr['cash_left'] ?? 0) < ($totalCashIn * 2);

        $disqualified = !$flipViable && !$rentalViable && !$brrrrViable;

        // Determine best strategy by simple scoring priority.
        $bestStrategy = null;

        if (!$disqualified) {
            $strategies = [];

            if ($flipViable) {
                $strategies['flip'] = ($financials['cash_roi'] ?? 0);
            }

            if ($rentalViable) {
                $strategies['rental'] = ($rental['cap_rate'] ?? 0) * 10;
            }

            if ($brrrrViable) {
                $strategies['brrrr'] = ($brrrr['dscr'] ?? 0) * 50;
            }

            if ($strategies !== []) {
                arsort($strategies);
                $bestStrategy = array_key_first($strategies);
            }
        }

        return [
            'flip_viable'   => $flipViable,
            'rental_viable' => $rentalViable,
            'brrrr_viable'  => $brrrrViable,
            'disqualified'  => $disqualified,
            'best_strategy' => $bestStrategy,
        ];
    }

    /**
     * Calculate composite scores for overall, flip, rental, and BRRRR strategies.
     *
     * @return array{total_score: float, financial_score: float, property_score: float,
     *               location_score: float, market_score: float, flip_score: float,
     *               rental_score: float, brrrr_score: float}
     */
    private function calculateCompositeScore(array $financials, array $property, array $arvResult): array
    {
        $financialScore = $this->scoreFinancial($financials);
        $propertyScore  = $this->scoreProperty($property);
        $locationScore  = 50.0; // Placeholder.
        $marketScore    = $this->scoreMarket($financials);

        $totalScore  = 0.70 * $financialScore + 0.20 * $propertyScore + 0.10 * $marketScore;
        $flipScore   = $totalScore; // Same weighting for flip.
        $rentalScore = 0.60 * $financialScore + 0.30 * $propertyScore + 0.10 * $marketScore;
        $brrrrScore  = 0.65 * $financialScore + 0.25 * $propertyScore + 0.10 * $marketScore;

        return [
            'total_score'     => round($totalScore, 1),
            'financial_score' => round($financialScore, 1),
            'property_score'  => round($propertyScore, 1),
            'location_score'  => round($locationScore, 1),
            'market_score'    => round($marketScore, 1),
            'flip_score'      => round($flipScore, 1),
            'rental_score'    => round($rentalScore, 1),
            'brrrr_score'     => round($brrrrScore, 1),
        ];
    }

    /**
     * Financial score (0-100).
     */
    private function scoreFinancial(array $financials): float
    {
        $listPrice         = (float) ($financials['list_price'] ?? 0);
        $arv               = (float) ($financials['arv'] ?? 0);
        $originalListPrice = (float) ($financials['original_list_price'] ?? $listPrice);
        $dom               = (int) ($financials['days_on_market'] ?? 0);
        $cashRoi           = (float) ($financials['cash_roi'] ?? 0);

        // Price-to-ARV ratio (37.5%).
        $ratio = ($arv > 0) ? $listPrice / $arv : 1.0;

        if ($ratio < 0.65) {
            $ratioScore = 100;
        } elseif ($ratio < 0.70) {
            $ratioScore = 80;
        } elseif ($ratio < 0.75) {
            $ratioScore = 60;
        } elseif ($ratio < 0.80) {
            $ratioScore = 40;
        } else {
            $ratioScore = 20;
        }

        // Price reduction (25%).
        $reductionPct = 0.0;

        if ($originalListPrice > 0 && $originalListPrice > $listPrice) {
            $reductionPct = (($originalListPrice - $listPrice) / $originalListPrice) * 100;
        }

        if ($reductionPct > 15) {
            $reductionScore = 100;
        } elseif ($reductionPct > 10) {
            $reductionScore = 80;
        } elseif ($reductionPct > 5) {
            $reductionScore = 60;
        } elseif ($reductionPct > 1) {
            $reductionScore = 40;
        } else {
            $reductionScore = 20;
        }

        // DOM motivation (12.5%).
        if ($dom > 90) {
            $domScore = 100;
        } elseif ($dom > 60) {
            $domScore = 70;
        } elseif ($dom > 30) {
            $domScore = 40;
        } else {
            $domScore = 20;
        }

        // ROI score (25%).
        if ($cashRoi > 30) {
            $roiScore = 100;
        } elseif ($cashRoi > 20) {
            $roiScore = 80;
        } elseif ($cashRoi > 15) {
            $roiScore = 60;
        } elseif ($cashRoi > 10) {
            $roiScore = 40;
        } else {
            $roiScore = 20;
        }

        return $ratioScore * 0.375 + $reductionScore * 0.25 + $domScore * 0.125 + $roiScore * 0.25;
    }

    /**
     * Property score (0-100).
     */
    private function scoreProperty(array $property): float
    {
        $lotSizeAcres = (float) ($property['lot_size_acres'] ?? 0);
        $livingArea   = (int) ($property['living_area'] ?? 0);
        $yearBuilt    = (int) ($property['year_built'] ?? 0);
        $bedrooms     = (int) ($property['bedrooms_total'] ?? 0);

        // Lot size (35%).
        if ($lotSizeAcres > 0.5) {
            $lotScore = 100;
        } elseif ($lotSizeAcres > 0.25) {
            $lotScore = 80;
        } elseif ($lotSizeAcres > 0.15) {
            $lotScore = 60;
        } elseif ($lotSizeAcres > 0.10) {
            $lotScore = 40;
        } else {
            $lotScore = 20;
        }

        // Square footage (20%).
        if ($livingArea > 2500) {
            $sqftScore = 100;
        } elseif ($livingArea > 2000) {
            $sqftScore = 80;
        } elseif ($livingArea > 1500) {
            $sqftScore = 60;
        } elseif ($livingArea > 1000) {
            $sqftScore = 40;
        } else {
            $sqftScore = 20;
        }

        // Renovation age — sweet spot is 21-70 years (30%).
        $age = $yearBuilt > 0 ? max(0, 2026 - $yearBuilt) : 0;

        if ($age >= 41 && $age <= 70) {
            $ageScore = 100;
        } elseif ($age >= 21 && $age <= 40) {
            $ageScore = 80;
        } elseif ($age >= 16 && $age <= 20) {
            $ageScore = 60;
        } elseif ($age >= 6 && $age <= 15) {
            $ageScore = 40;
        } else {
            // <= 5 years or > 100 years or unknown.
            $ageScore = 20;
        }

        // Bedrooms (15%).
        if ($bedrooms >= 4) {
            $bedScore = 100;
        } elseif ($bedrooms === 3) {
            $bedScore = 80;
        } elseif ($bedrooms === 2) {
            $bedScore = 60;
        } else {
            $bedScore = 40;
        }

        return $lotScore * 0.35 + $sqftScore * 0.20 + $ageScore * 0.30 + $bedScore * 0.15;
    }

    /**
     * Market score (0-100).
     */
    private function scoreMarket(array $financials): float
    {
        $dom               = (int) ($financials['days_on_market'] ?? 0);
        $listPrice         = (float) ($financials['list_price'] ?? 0);
        $originalListPrice = (float) ($financials['original_list_price'] ?? $listPrice);

        // DOM (40%).
        if ($dom > 90) {
            $domScore = 100;
        } elseif ($dom > 60) {
            $domScore = 70;
        } elseif ($dom > 30) {
            $domScore = 40;
        } else {
            $domScore = 20;
        }

        // Price reduction (30%).
        $reductionPct = 0.0;

        if ($originalListPrice > 0 && $originalListPrice > $listPrice) {
            $reductionPct = (($originalListPrice - $listPrice) / $originalListPrice) * 100;
        }

        if ($reductionPct > 15) {
            $reductionScore = 100;
        } elseif ($reductionPct > 10) {
            $reductionScore = 80;
        } elseif ($reductionPct > 5) {
            $reductionScore = 60;
        } elseif ($reductionPct > 1) {
            $reductionScore = 40;
        } else {
            $reductionScore = 20;
        }

        // Season (30%) — month-based.
        $month = (int) gmdate('n');

        if ($month >= 1 && $month <= 3) {
            $seasonScore = 30;
        } elseif ($month >= 4 && $month <= 6) {
            $seasonScore = 80;
        } elseif ($month >= 7 && $month <= 9) {
            $seasonScore = 100;
        } else {
            $seasonScore = 60;
        }

        return $domScore * 0.40 + $reductionScore * 0.30 + $seasonScore * 0.30;
    }

    /**
     * Compute the coefficient of variation of comp adjusted prices.
     */
    private function computePriceVarianceCv(array $comparables): float
    {
        $prices = [];

        foreach ($comparables as $item) {
            $price = (float) ($item['adjusted_price'] ?? 0);

            if ($price > 0) {
                $prices[] = $price;
            }
        }

        if (count($prices) < 2) {
            return 0.0;
        }

        $mean   = array_sum($prices) / count($prices);
        $sumSqD = 0.0;

        foreach ($prices as $p) {
            $sumSqD += ($p - $mean) ** 2;
        }

        $stdDev = sqrt($sumSqD / count($prices));

        return $mean > 0 ? $stdDev / $mean : 0.0;
    }

    /**
     * Persist analysis data to the database.
     */
    private function saveAnalysis(array $result, ?int $reportId): int|false
    {
        $now = current_time('mysql');

        return $this->analysisRepo->create([
            'report_id'            => $reportId,
            'listing_id'           => $result['listing_id'],
            'address'              => $result['address'],
            'city'                 => $result['city'],
            'state'                => $result['state'],
            'zip'                  => $result['zip'],
            'list_price'           => $result['list_price'],
            'property_type'        => $result['property_type'],
            'bedrooms_total'       => $result['bedrooms_total'],
            'bathrooms_total'      => $result['bathrooms_total'],
            'living_area'          => $result['living_area'],
            'lot_size_acres'       => $result['lot_size_acres'],
            'year_built'           => $result['year_built'],
            'garage_spaces'        => $result['garage_spaces'],
            'latitude'             => $result['latitude'],
            'longitude'            => $result['longitude'],
            'days_on_market'       => $result['days_on_market'],
            'original_list_price'  => $result['original_list_price'],
            'estimated_arv'        => $result['estimated_arv'],
            'arv_confidence'       => $result['arv_confidence'],
            'arv_confidence_score' => $result['arv_confidence_score'],
            'comp_count'           => $result['comp_count'],
            'avg_comp_ppsf'        => $result['avg_comp_ppsf'],
            'neighborhood_ceiling' => $result['neighborhood_ceiling'],
            'estimated_rehab_cost' => $result['estimated_rehab_cost'],
            'rehab_per_sqft'       => $result['rehab_per_sqft'],
            'estimated_hold_months' => $result['estimated_hold_months'],
            'purchase_closing_cost' => $result['purchase_closing_cost'],
            'sale_costs'           => $result['sale_costs'],
            'holding_costs'        => $result['holding_costs'],
            'cash_profit'          => $result['cash_profit'],
            'cash_roi'             => $result['cash_roi'],
            'cash_investment'      => $result['cash_investment'],
            'financed_profit'      => $result['financed_profit'],
            'cash_on_cash_roi'     => $result['cash_on_cash_roi'],
            'annualized_roi'       => $result['annualized_roi'],
            'mao_classic'          => $result['mao_classic'],
            'mao_adjusted'         => $result['mao_adjusted'],
            'breakeven_arv'        => $result['breakeven_arv'],
            'total_score'          => $result['total_score'],
            'financial_score'      => $result['financial_score'],
            'property_score'       => $result['property_score'],
            'location_score'       => $result['location_score'],
            'market_score'         => $result['market_score'],
            'flip_score'           => $result['flip_score'],
            'rental_score'         => $result['rental_score'],
            'brrrr_score'          => $result['brrrr_score'],
            'best_strategy'        => $result['best_strategy'],
            'flip_viable'          => $result['flip_viable'] ? 1 : 0,
            'rental_viable'        => $result['rental_viable'] ? 1 : 0,
            'brrrr_viable'         => $result['brrrr_viable'] ? 1 : 0,
            'disqualified'         => $result['disqualified'] ? 1 : 0,
            'dq_reason'            => $result['dq_reason'],
            'deal_risk_grade'      => $result['deal_risk_grade'],
            'rental_analysis'      => $result['rental_analysis'],
            'run_date'             => $now,
        ]);
    }

    /**
     * Persist comparable data to the database.
     *
     * @param int   $analysisId Parent analysis ID.
     * @param array $comparables Enriched comp array from ARV service.
     */
    private function saveComparables(int $analysisId, array $comparables): void
    {
        foreach ($comparables as $item) {
            $comp = $item['comp'] ?? null;

            if ($comp === null) {
                continue;
            }

            $remarks   = strtolower((string) ($comp->remarks ?? ''));
            $renovated = (
                str_contains($remarks, 'renovated')
                || str_contains($remarks, 'updated')
                || str_contains($remarks, 'remodeled')
            );

            $distressed = (
                str_contains($remarks, 'foreclosure')
                || str_contains($remarks, 'short sale')
                || str_contains($remarks, 'bank owned')
                || str_contains($remarks, 'reo')
            );

            $this->comparableRepo->create([
                'analysis_id'    => $analysisId,
                'listing_id'     => (string) ($comp->listing_id ?? ''),
                'address'        => (string) ($comp->address ?? ''),
                'city'           => (string) ($comp->city ?? ''),
                'close_price'    => (float) ($comp->close_price ?? 0),
                'close_date'     => (string) ($comp->close_date ?? ''),
                'adjusted_price' => (float) ($item['adjusted_price'] ?? 0),
                'adjustment_total' => (float) ($item['total_adj'] ?? 0),
                'adjustments'    => $item['adjustments'] ?? [],
                'distance_miles' => (float) ($comp->distance_miles ?? 0),
                'property_type'  => (string) ($comp->property_type ?? ''),
                'bedrooms_total' => (int) ($comp->bedrooms_total ?? 0),
                'bathrooms_total' => (float) ($comp->bathrooms_total ?? 0),
                'living_area'    => (int) ($comp->living_area ?? 0),
                'year_built'     => (int) ($comp->year_built ?? 0),
                'lot_size_acres' => (float) ($comp->lot_size_acres ?? 0),
                'garage_spaces'  => (int) ($comp->garage_spaces ?? 0),
                'days_on_market' => (int) ($comp->days_on_market ?? 0),
                'weight'         => (float) ($item['weight'] ?? 0),
                'is_renovated'   => $renovated ? 1 : 0,
                'is_distressed'  => $distressed ? 1 : 0,
            ]);
        }
    }
}

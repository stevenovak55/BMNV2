<?php

declare(strict_types=1);

namespace BMN\Flip\Service;

/**
 * Calculate all financial metrics for flip analysis: rehab costs, holding costs,
 * transaction costs, profit, ROI, MAO, and multi-strategy (flip/rental/BRRRR).
 *
 * This is a pure logic service with NO dependencies (no DB, no WP functions).
 * All data is passed in.
 */
class FinancialService
{
    // ------------------------------------------------------------------
    // Transaction costs
    // ------------------------------------------------------------------
    private const COMMISSION_RATE = 0.045;
    private const CLOSING_RATE_BUY = 0.015;
    private const CLOSING_RATE_SELL = 0.01;
    private const TRANSFER_TAX_RATE = 0.00456; // MA deed excise tax

    // ------------------------------------------------------------------
    // Holding costs
    // ------------------------------------------------------------------
    private const INSURANCE_RATE = 0.005;       // annual, % of list price
    private const DEFAULT_TAX_RATE = 0.013;     // MA average property tax rate
    private const MONTHLY_UTILITIES = 350.0;

    // ------------------------------------------------------------------
    // Hard money financing
    // ------------------------------------------------------------------
    private const HARD_MONEY_RATE = 0.105;      // 10.5% annual
    private const HARD_MONEY_POINTS = 0.02;     // 2% origination
    private const HARD_MONEY_LTV = 0.80;        // 80% LTV

    // ------------------------------------------------------------------
    // Rehab
    // ------------------------------------------------------------------
    private const LEAD_PAINT_ALLOWANCE = 8000.0; // pre-1978 properties
    private const MIN_REHAB_PPSF = 2.0;
    private const MAX_REHAB_PPSF = 65.0;

    // ------------------------------------------------------------------
    // Rental
    // ------------------------------------------------------------------
    private const DEFAULT_VACANCY_RATE = 0.05;
    private const DEFAULT_MANAGEMENT_RATE = 0.08;
    private const DEFAULT_MAINTENANCE_RATE = 0.01;
    private const DEFAULT_CAPEX_RATE = 0.05;
    private const DEFAULT_RENTAL_INSURANCE_RATE = 0.006;
    private const DEPRECIATION_YEARS = 27.5;
    private const LAND_VALUE_PCT = 0.20;
    private const DEFAULT_TAX_BRACKET = 0.32;

    // ------------------------------------------------------------------
    // BRRRR
    // ------------------------------------------------------------------
    private const BRRRR_REFI_LTV = 0.75;
    private const BRRRR_REFI_RATE = 0.072;
    private const BRRRR_REFI_TERM = 30;

    public function __construct()
    {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Estimate rehab cost based on property age, condition, and size.
     *
     * @return array{total: float, per_sqft: float, contingency_rate: float,
     *               lead_paint: float, base_cost: float}
     */
    public function estimateRehabCost(array $property): array
    {
        $yearBuilt  = (int) ($property['year_built'] ?? 0);
        $livingArea = (int) ($property['living_area'] ?? 0);

        if ($livingArea <= 0) {
            return [
                'total'            => 0.0,
                'per_sqft'         => 0.0,
                'contingency_rate' => 0.0,
                'lead_paint'       => 0.0,
                'base_cost'        => 0.0,
            ];
        }

        $currentYear = 2026;
        $age         = $yearBuilt > 0 ? max(0, $currentYear - $yearBuilt) : 30;

        // Base PPSF from age, clamped.
        $basePpsf = min(self::MAX_REHAB_PPSF, max(5.0, 10.0 + $age * 0.7));

        // Age-condition multiplier.
        if ($age <= 5) {
            $ageCondMult = 0.10;
        } elseif ($age <= 10) {
            $ageCondMult = 0.30;
        } elseif ($age <= 15) {
            $ageCondMult = 0.50;
        } elseif ($age <= 20) {
            $ageCondMult = 0.75;
        } else {
            $ageCondMult = 1.0;
        }

        $effectivePpsf = max(self::MIN_REHAB_PPSF, $basePpsf * $ageCondMult);
        $baseCost      = $livingArea * $effectivePpsf;

        // Contingency rate scales with rehab intensity.
        if ($effectivePpsf <= 20) {
            $contingencyRate = 0.08;
        } elseif ($effectivePpsf <= 35) {
            $contingencyRate = 0.12;
        } elseif ($effectivePpsf <= 50) {
            $contingencyRate = 0.15;
        } else {
            $contingencyRate = 0.20;
        }

        $contingency = $baseCost * $contingencyRate;

        // Lead paint allowance for pre-1978 buildings.
        $leadPaint = ($yearBuilt > 0 && $yearBuilt < 1978) ? self::LEAD_PAINT_ALLOWANCE : 0.0;

        $total = $baseCost + $contingency + $leadPaint;

        return [
            'total'            => round($total, 2),
            'per_sqft'         => round($effectivePpsf, 2),
            'contingency_rate' => $contingencyRate,
            'lead_paint'       => $leadPaint,
            'base_cost'        => round($baseCost, 2),
        ];
    }

    /**
     * Estimate the total hold period in months (rehab + sale + permits).
     *
     * @param float $rehabPerSqft Rehab cost per square foot.
     * @param int   $avgDom       Average days on market for the area.
     */
    public function estimateHoldPeriod(float $rehabPerSqft, int $avgDom): int
    {
        // Rehab duration based on intensity.
        if ($rehabPerSqft <= 20) {
            $rehabMonths = 1;
        } elseif ($rehabPerSqft <= 35) {
            $rehabMonths = 2;
        } elseif ($rehabPerSqft <= 50) {
            $rehabMonths = 4;
        } else {
            $rehabMonths = 6;
        }

        $saleMonths   = max(1, (int) ceil($avgDom / 30));
        $permitBuffer = ($rehabPerSqft > 35) ? 1 : 0;

        return $rehabMonths + $saleMonths + $permitBuffer;
    }

    /**
     * Calculate purchase and sale transaction costs.
     *
     * @return array{purchase_closing: float, sale_costs: float,
     *               transfer_tax_buy: float, transfer_tax_sell: float}
     */
    public function calculateTransactionCosts(float $listPrice, float $arv): array
    {
        $purchaseClosing = $listPrice * self::CLOSING_RATE_BUY + $listPrice * self::TRANSFER_TAX_RATE;
        $saleCosts       = $arv * (self::COMMISSION_RATE + self::CLOSING_RATE_SELL) + $arv * self::TRANSFER_TAX_RATE;

        return [
            'purchase_closing' => round($purchaseClosing, 2),
            'sale_costs'       => round($saleCosts, 2),
            'transfer_tax_buy' => round($listPrice * self::TRANSFER_TAX_RATE, 2),
            'transfer_tax_sell' => round($arv * self::TRANSFER_TAX_RATE, 2),
        ];
    }

    /**
     * Calculate monthly holding costs over the hold period.
     *
     * @return array{monthly_tax: float, monthly_insurance: float,
     *               monthly_utilities: float, total: float}
     */
    public function calculateHoldingCosts(float $listPrice, int $holdMonths, float $taxRate = 0): array
    {
        $effectiveTaxRate = $taxRate > 0 ? $taxRate : self::DEFAULT_TAX_RATE;

        $monthlyTax       = $listPrice * $effectiveTaxRate / 12;
        $monthlyInsurance = $listPrice * self::INSURANCE_RATE / 12;
        $total            = ($monthlyTax + $monthlyInsurance + self::MONTHLY_UTILITIES) * $holdMonths;

        return [
            'monthly_tax'       => round($monthlyTax, 2),
            'monthly_insurance' => round($monthlyInsurance, 2),
            'monthly_utilities' => self::MONTHLY_UTILITIES,
            'total'             => round($total, 2),
        ];
    }

    /**
     * Calculate profit and ROI for an all-cash purchase scenario.
     *
     * @return array{profit: float, roi: float, investment: float}
     */
    public function calculateCashScenario(
        float $listPrice,
        float $arv,
        float $rehabCost,
        float $purchaseClosing,
        float $saleCosts,
        float $holdingCosts,
    ): array {
        $profit     = $arv - $listPrice - $rehabCost - $purchaseClosing - $saleCosts - $holdingCosts;
        $investment = $listPrice + $rehabCost + $purchaseClosing + $holdingCosts;
        $roi        = $investment > 0 ? ($profit / $investment) * 100 : 0.0;

        return [
            'profit'     => round($profit, 2),
            'roi'        => round($roi, 2),
            'investment' => round($investment, 2),
        ];
    }

    /**
     * Calculate profit and ROI for a hard-money financed scenario.
     *
     * @return array{profit: float, cash_on_cash_roi: float, cash_invested: float,
     *               loan_amount: float, financing_costs: float, annualized_roi: float}
     */
    public function calculateFinancedScenario(
        float $listPrice,
        float $arv,
        float $rehabCost,
        float $purchaseClosing,
        float $saleCosts,
        float $holdingCosts,
        int $holdMonths,
    ): array {
        $loanAmount      = $listPrice * self::HARD_MONEY_LTV;
        $origination     = $loanAmount * self::HARD_MONEY_POINTS;
        $monthlyInterest = $loanAmount * (self::HARD_MONEY_RATE / 12);
        $financingCosts  = $origination + ($monthlyInterest * $holdMonths);

        $cashProfit   = $arv - $listPrice - $rehabCost - $purchaseClosing - $saleCosts - $holdingCosts;
        $profit       = $cashProfit - $financingCosts;
        $cashInvested = ($listPrice * (1 - self::HARD_MONEY_LTV)) + $rehabCost + $purchaseClosing;

        $cashOnCashRoi = $cashInvested > 0 ? ($profit / $cashInvested) * 100 : 0.0;
        $annualizedRoi = $holdMonths > 0
            ? (pow(1 + $cashOnCashRoi / 100, 12 / $holdMonths) - 1) * 100
            : 0.0;

        return [
            'profit'           => round($profit, 2),
            'cash_on_cash_roi' => round($cashOnCashRoi, 2),
            'cash_invested'    => round($cashInvested, 2),
            'loan_amount'      => round($loanAmount, 2),
            'financing_costs'  => round($financingCosts, 2),
            'annualized_roi'   => round($annualizedRoi, 2),
        ];
    }

    /**
     * Calculate Maximum Allowable Offer (MAO).
     *
     * @return array{classic: float, adjusted: float}
     */
    public function calculateMao(
        float $arv,
        float $rehabCost,
        float $holdingCosts = 0,
        float $financingCosts = 0,
    ): array {
        $classic  = ($arv * 0.70) - $rehabCost;
        $adjusted = ($arv * 0.70) - $rehabCost - $holdingCosts - $financingCosts;

        return [
            'classic'  => round($classic, 2),
            'adjusted' => round($adjusted, 2),
        ];
    }

    /**
     * Calculate the minimum ARV needed to break even.
     */
    public function calculateBreakevenArv(
        float $listPrice,
        float $rehabCost,
        float $purchaseClosing,
        float $holdingCosts,
        float $financingCosts,
    ): float {
        $saleCostPct = self::COMMISSION_RATE + self::CLOSING_RATE_SELL + self::TRANSFER_TAX_RATE;
        $totalCosts  = $listPrice + $rehabCost + $purchaseClosing + $holdingCosts + $financingCosts;

        return round($totalCosts / (1 - $saleCostPct), 2);
    }

    /**
     * Analyze the property as a rental hold.
     *
     * @return array{monthly_rent: float, annual_gross: float, vacancy_loss: float,
     *               operating_expenses: float, noi: float, cap_rate: float,
     *               cash_on_cash: float, grm: float, annual_depreciation: float,
     *               tax_shelter: float}
     */
    public function calculateRentalAnalysis(
        float $arv,
        float $monthlyRent,
        float $totalInvestment,
        float $taxRate = 0,
    ): array {
        $annualGross  = $monthlyRent * 12;
        $vacancyLoss  = $annualGross * self::DEFAULT_VACANCY_RATE;
        $management   = $annualGross * self::DEFAULT_MANAGEMENT_RATE;
        $maintenance  = $arv * self::DEFAULT_MAINTENANCE_RATE;
        $insurance    = $arv * self::DEFAULT_RENTAL_INSURANCE_RATE;
        $capex        = $annualGross * self::DEFAULT_CAPEX_RATE;
        $effectiveTax = $taxRate > 0 ? $taxRate : self::DEFAULT_TAX_RATE;
        $propertyTax  = $arv * $effectiveTax;

        $operatingExpenses = $vacancyLoss + $management + $maintenance + $insurance + $capex + $propertyTax;
        $noi               = $annualGross - $operatingExpenses;
        $capRate           = $arv > 0 ? ($noi / $arv) * 100 : 0.0;
        $cashOnCash        = $totalInvestment > 0 ? ($noi / $totalInvestment) * 100 : 0.0;
        $grm               = $annualGross > 0 ? $totalInvestment / $annualGross : 0.0;

        $depreciable         = $arv * (1 - self::LAND_VALUE_PCT);
        $annualDepreciation  = $depreciable / self::DEPRECIATION_YEARS;
        $taxShelter          = $annualDepreciation * self::DEFAULT_TAX_BRACKET;

        return [
            'monthly_rent'        => round($monthlyRent, 2),
            'annual_gross'        => round($annualGross, 2),
            'vacancy_loss'        => round($vacancyLoss, 2),
            'operating_expenses'  => round($operatingExpenses, 2),
            'noi'                 => round($noi, 2),
            'cap_rate'            => round($capRate, 2),
            'cash_on_cash'        => round($cashOnCash, 2),
            'grm'                 => round($grm, 2),
            'annual_depreciation' => round($annualDepreciation, 2),
            'tax_shelter'         => round($taxShelter, 2),
        ];
    }

    /**
     * BRRRR (Buy, Rehab, Rent, Refinance, Repeat) analysis.
     *
     * @return array{refi_loan: float, monthly_payment: float,
     *               annual_debt_service: float, post_refi_cash_flow: float,
     *               dscr: float, cash_left: float}
     */
    public function calculateBrrrr(float $arv, float $noi, float $totalCashIn): array
    {
        $refiLoan    = $arv * self::BRRRR_REFI_LTV;
        $monthlyRate = self::BRRRR_REFI_RATE / 12;
        $numPayments = self::BRRRR_REFI_TERM * 12;

        // Standard amortisation formula.
        $monthlyPayment = $refiLoan
            * ($monthlyRate * pow(1 + $monthlyRate, $numPayments))
            / (pow(1 + $monthlyRate, $numPayments) - 1);

        $annualDebtService = $monthlyPayment * 12;
        $postRefiCashFlow  = $noi - $annualDebtService;
        $dscr              = $annualDebtService > 0 ? $noi / $annualDebtService : 0.0;
        $cashLeft          = $totalCashIn - $refiLoan;

        return [
            'refi_loan'           => round($refiLoan, 2),
            'monthly_payment'     => round($monthlyPayment, 2),
            'annual_debt_service' => round($annualDebtService, 2),
            'post_refi_cash_flow' => round($postRefiCashFlow, 2),
            'dscr'                => round($dscr, 2),
            'cash_left'           => round($cashLeft, 2),
        ];
    }

    /**
     * Simple monthly rent estimation from living area.
     */
    public function estimateMonthlyRent(array $property): float
    {
        $livingArea = (int) ($property['living_area'] ?? 0);

        if ($livingArea <= 0) {
            return 0.0;
        }

        return round($livingArea * 1.80, 2);
    }

    /**
     * Grade overall deal risk.
     *
     * @return array{grade: string, score: float, factors: array}
     */
    public function gradeRisk(
        float $arvConfidence,
        float $breakevenArv,
        float $arv,
        float $priceVarianceCv,
        int $avgDom,
        int $compCount,
    ): array {
        $factors = [];

        // Factor 1: ARV confidence (35%).
        if ($arvConfidence >= 75) {
            $confFactor = 100;
        } elseif ($arvConfidence >= 50) {
            $confFactor = 70;
        } elseif ($arvConfidence >= 20) {
            $confFactor = 40;
        } else {
            $confFactor = 10;
        }
        $factors['arv_confidence'] = $confFactor;

        // Factor 2: Margin cushion — how far ARV exceeds breakeven (25%).
        if ($arv > 0 && $breakevenArv > 0) {
            $cushion = ($arv - $breakevenArv) / $arv;

            if ($cushion >= 0.30) {
                $marginFactor = 100;
            } elseif ($cushion >= 0.20) {
                $marginFactor = 80;
            } elseif ($cushion >= 0.10) {
                $marginFactor = 60;
            } elseif ($cushion >= 0.0) {
                $marginFactor = 40;
            } else {
                $marginFactor = 10;
            }
        } else {
            $marginFactor = 10;
        }
        $factors['margin_cushion'] = $marginFactor;

        // Factor 3: Comp consistency — inverse of CV (20%).
        if ($priceVarianceCv < 0.10) {
            $consistencyFactor = 100;
        } elseif ($priceVarianceCv < 0.20) {
            $consistencyFactor = 70;
        } elseif ($priceVarianceCv < 0.30) {
            $consistencyFactor = 40;
        } else {
            $consistencyFactor = 15;
        }
        $factors['comp_consistency'] = $consistencyFactor;

        // Factor 4: Market velocity — DOM (10%).
        if ($avgDom > 90) {
            $velocityFactor = 30;
        } elseif ($avgDom > 60) {
            $velocityFactor = 50;
        } elseif ($avgDom > 30) {
            $velocityFactor = 70;
        } else {
            $velocityFactor = 100;
        }
        $factors['market_velocity'] = $velocityFactor;

        // Factor 5: Comp count (10%).
        if ($compCount >= 8) {
            $countFactor = 100;
        } elseif ($compCount >= 5) {
            $countFactor = 80;
        } elseif ($compCount >= 3) {
            $countFactor = 60;
        } elseif ($compCount >= 1) {
            $countFactor = 30;
        } else {
            $countFactor = 0;
        }
        $factors['comp_count_factor'] = $countFactor;

        // Weighted composite.
        $score = (
            $confFactor * 0.35
            + $marginFactor * 0.25
            + $consistencyFactor * 0.20
            + $velocityFactor * 0.10
            + $countFactor * 0.10
        );

        // Grade mapping.
        if ($score >= 80) {
            $grade = 'A';
        } elseif ($score >= 65) {
            $grade = 'B';
        } elseif ($score >= 50) {
            $grade = 'C';
        } elseif ($score >= 35) {
            $grade = 'D';
        } else {
            $grade = 'F';
        }

        return [
            'grade'   => $grade,
            'score'   => round($score, 2),
            'factors' => $factors,
        ];
    }
}

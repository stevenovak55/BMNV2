<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Service;

use BMN\Flip\Service\FinancialService;
use PHPUnit\Framework\TestCase;

class FinancialServiceTest extends TestCase
{
    private FinancialService $service;

    protected function setUp(): void
    {
        $this->service = new FinancialService();
    }

    // ------------------------------------------------------------------
    // estimateRehabCost
    // ------------------------------------------------------------------

    public function testEstimateRehabCostNewProperty(): void
    {
        $property = [
            'year_built' => 2022,
            'living_area' => 2000,
        ];

        $result = $this->service->estimateRehabCost($property);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_sqft', $result);
        $this->assertArrayHasKey('contingency_rate', $result);
        $this->assertArrayHasKey('lead_paint', $result);
        $this->assertArrayHasKey('base_cost', $result);

        // Age = 2026 - 2022 = 4, so ageCondMult = 0.10 (<=5).
        // basePpsf = min(65, max(5, 10 + 4*0.7)) = 12.8
        // effectivePpsf = max(2.0, 12.8 * 0.10) = max(2.0, 1.28) = 2.0
        // baseCost = 2000 * 2.0 = 4000
        // contingency = 4000 * 0.08 = 320 (since effectivePpsf=2.0 <= 20)
        // lead_paint = 0 (2022 >= 1978)
        // total = 4000 + 320 = 4320
        $this->assertEquals(0.0, $result['lead_paint']);
        $this->assertLessThan(10000, $result['total']);
    }

    public function testEstimateRehabCostOldProperty(): void
    {
        $property = [
            'year_built' => 1950,
            'living_area' => 2000,
        ];

        $result = $this->service->estimateRehabCost($property);

        // Age = 2026 - 1950 = 76, ageCondMult = 1.0 (>20).
        // basePpsf = min(65, max(5, 10 + 76*0.7)) = min(65, 63.2) = 63.2
        // effectivePpsf = max(2.0, 63.2 * 1.0) = 63.2
        // baseCost = 2000 * 63.2 = 126400
        // Much higher than new property.
        $this->assertGreaterThan(50000, $result['total']);
        $this->assertGreaterThan(40, $result['per_sqft']);
    }

    public function testEstimateRehabCostLeadPaint(): void
    {
        $property = [
            'year_built' => 1970,
            'living_area' => 1500,
        ];

        $result = $this->service->estimateRehabCost($property);

        // 1970 < 1978, so lead paint allowance = $8000.
        $this->assertEquals(8000.0, $result['lead_paint']);
    }

    public function testEstimateRehabCostNoLeadPaint(): void
    {
        $property = [
            'year_built' => 1980,
            'living_area' => 1500,
        ];

        $result = $this->service->estimateRehabCost($property);

        // 1980 >= 1978, so no lead paint.
        $this->assertEquals(0.0, $result['lead_paint']);
    }

    public function testEstimateRehabCostMinimum(): void
    {
        $property = [
            'year_built' => 2024,
            'living_area' => 1000,
        ];

        $result = $this->service->estimateRehabCost($property);

        // per_sqft should never be below MIN_REHAB_PPSF (2.0).
        $this->assertGreaterThanOrEqual(2.0, $result['per_sqft']);
    }

    // ------------------------------------------------------------------
    // estimateHoldPeriod
    // ------------------------------------------------------------------

    public function testEstimateHoldPeriodCosmetic(): void
    {
        // Low rehab (<=20 ppsf) -> 1 month rehab. avgDom=15 -> 1 month sale. No permit.
        $result = $this->service->estimateHoldPeriod(15.0, 15);

        $this->assertEquals(2, $result); // 1 rehab + 1 sale
    }

    public function testEstimateHoldPeriodMajor(): void
    {
        // High rehab (>50 ppsf) -> 6 months rehab. avgDom=60 -> 2 month sale. +1 permit.
        $result = $this->service->estimateHoldPeriod(55.0, 60);

        $this->assertEquals(9, $result); // 6 rehab + 2 sale + 1 permit
    }

    public function testEstimateHoldPeriodWithPermitBuffer(): void
    {
        // Rehab > 35 ppsf -> permit buffer of +1.
        $result = $this->service->estimateHoldPeriod(40.0, 30);

        // 40 ppsf -> rehabMonths=4 (36-50 range). avgDom=30 -> saleMonths=1. permit=1.
        $this->assertEquals(6, $result); // 4 + 1 + 1
    }

    // ------------------------------------------------------------------
    // calculateTransactionCosts
    // ------------------------------------------------------------------

    public function testCalculateTransactionCosts(): void
    {
        $listPrice = 400000.0;
        $arv = 550000.0;

        $result = $this->service->calculateTransactionCosts($listPrice, $arv);

        $this->assertArrayHasKey('purchase_closing', $result);
        $this->assertArrayHasKey('sale_costs', $result);
        $this->assertArrayHasKey('transfer_tax_buy', $result);
        $this->assertArrayHasKey('transfer_tax_sell', $result);

        // purchase_closing = listPrice * 0.015 + listPrice * 0.00456
        $expectedPurchaseClosing = round($listPrice * 0.015 + $listPrice * 0.00456, 2);
        $this->assertEquals($expectedPurchaseClosing, $result['purchase_closing']);

        // sale_costs = arv * (0.045 + 0.01) + arv * 0.00456
        $expectedSaleCosts = round($arv * (0.045 + 0.01) + $arv * 0.00456, 2);
        $this->assertEquals($expectedSaleCosts, $result['sale_costs']);
    }

    // ------------------------------------------------------------------
    // calculateHoldingCosts
    // ------------------------------------------------------------------

    public function testCalculateHoldingCosts(): void
    {
        $listPrice = 400000.0;
        $holdMonths = 6;
        $taxRate = 0.015;

        $result = $this->service->calculateHoldingCosts($listPrice, $holdMonths, $taxRate);

        $this->assertArrayHasKey('monthly_tax', $result);
        $this->assertArrayHasKey('monthly_insurance', $result);
        $this->assertArrayHasKey('monthly_utilities', $result);
        $this->assertArrayHasKey('total', $result);

        $expectedMonthlyTax = round($listPrice * $taxRate / 12, 2);
        $this->assertEquals($expectedMonthlyTax, $result['monthly_tax']);
        $this->assertEquals(350.0, $result['monthly_utilities']);

        $expectedMonthlyInsurance = round($listPrice * 0.005 / 12, 2);
        $this->assertEquals($expectedMonthlyInsurance, $result['monthly_insurance']);
    }

    public function testCalculateHoldingCostsDefaultTaxRate(): void
    {
        $listPrice = 400000.0;
        $holdMonths = 4;

        // taxRate=0 should use default rate of 0.013.
        $result = $this->service->calculateHoldingCosts($listPrice, $holdMonths, 0);

        $expectedMonthlyTax = round($listPrice * 0.013 / 12, 2);
        $this->assertEquals($expectedMonthlyTax, $result['monthly_tax']);
    }

    // ------------------------------------------------------------------
    // calculateCashScenario
    // ------------------------------------------------------------------

    public function testCalculateCashScenarioProfit(): void
    {
        $result = $this->service->calculateCashScenario(
            listPrice: 300000,
            arv: 500000,
            rehabCost: 50000,
            purchaseClosing: 6000,
            saleCosts: 30000,
            holdingCosts: 10000,
        );

        $this->assertArrayHasKey('profit', $result);
        $this->assertArrayHasKey('roi', $result);
        $this->assertArrayHasKey('investment', $result);

        // profit = 500000 - 300000 - 50000 - 6000 - 30000 - 10000 = 104000
        $this->assertEquals(104000.0, $result['profit']);
        $this->assertGreaterThan(0, $result['roi']);

        // investment = 300000 + 50000 + 6000 + 10000 = 366000
        $this->assertEquals(366000.0, $result['investment']);
    }

    public function testCalculateCashScenarioLoss(): void
    {
        $result = $this->service->calculateCashScenario(
            listPrice: 500000,
            arv: 450000,
            rehabCost: 60000,
            purchaseClosing: 8000,
            saleCosts: 25000,
            holdingCosts: 12000,
        );

        // profit = 450000 - 500000 - 60000 - 8000 - 25000 - 12000 = -155000
        $this->assertLessThan(0, $result['profit']);
        $this->assertLessThan(0, $result['roi']);
    }

    // ------------------------------------------------------------------
    // calculateFinancedScenario
    // ------------------------------------------------------------------

    public function testCalculateFinancedScenario(): void
    {
        $result = $this->service->calculateFinancedScenario(
            listPrice: 300000,
            arv: 500000,
            rehabCost: 50000,
            purchaseClosing: 6000,
            saleCosts: 30000,
            holdingCosts: 10000,
            holdMonths: 6,
        );

        $this->assertArrayHasKey('profit', $result);
        $this->assertArrayHasKey('cash_on_cash_roi', $result);
        $this->assertArrayHasKey('cash_invested', $result);
        $this->assertArrayHasKey('loan_amount', $result);
        $this->assertArrayHasKey('financing_costs', $result);
        $this->assertArrayHasKey('annualized_roi', $result);

        // loan_amount = 300000 * 0.80 = 240000
        $this->assertEquals(240000.0, $result['loan_amount']);

        // financing_costs = origination + monthly_interest * holdMonths
        $origination = 240000 * 0.02;
        $monthlyInterest = 240000 * (0.105 / 12);
        $expectedFinancingCosts = round($origination + ($monthlyInterest * 6), 2);
        $this->assertEquals($expectedFinancingCosts, $result['financing_costs']);
    }

    public function testCalculateFinancedScenarioAnnualizedRoi(): void
    {
        $result = $this->service->calculateFinancedScenario(
            listPrice: 250000,
            arv: 450000,
            rehabCost: 40000,
            purchaseClosing: 5000,
            saleCosts: 25000,
            holdingCosts: 8000,
            holdMonths: 4,
        );

        $this->assertArrayHasKey('annualized_roi', $result);
        // With a 4-month hold, annualized ROI should be higher than raw ROI.
        if ($result['cash_on_cash_roi'] > 0) {
            $this->assertGreaterThan($result['cash_on_cash_roi'], $result['annualized_roi']);
        }
    }

    // ------------------------------------------------------------------
    // calculateMao
    // ------------------------------------------------------------------

    public function testCalculateMaoClassic(): void
    {
        $result = $this->service->calculateMao(
            arv: 500000,
            rehabCost: 50000,
        );

        $this->assertArrayHasKey('classic', $result);
        // classic = (500000 * 0.70) - 50000 = 350000 - 50000 = 300000
        $this->assertEquals(300000.0, $result['classic']);
    }

    public function testCalculateMaoAdjusted(): void
    {
        $result = $this->service->calculateMao(
            arv: 500000,
            rehabCost: 50000,
            holdingCosts: 10000,
            financingCosts: 8000,
        );

        $this->assertArrayHasKey('adjusted', $result);
        // adjusted = (500000 * 0.70) - 50000 - 10000 - 8000 = 350000 - 68000 = 282000
        $this->assertEquals(282000.0, $result['adjusted']);
    }

    // ------------------------------------------------------------------
    // calculateBreakevenArv
    // ------------------------------------------------------------------

    public function testCalculateBreakevenArv(): void
    {
        $result = $this->service->calculateBreakevenArv(
            listPrice: 300000,
            rehabCost: 50000,
            purchaseClosing: 6000,
            holdingCosts: 10000,
            financingCosts: 8000,
        );

        // saleCostPct = 0.045 + 0.01 + 0.00456 = 0.05956
        // totalCosts = 300000 + 50000 + 6000 + 10000 + 8000 = 374000
        // breakeven = 374000 / (1 - 0.05956) = 374000 / 0.94044
        $expectedBreakeven = round(374000 / (1 - 0.05956), 2);
        $this->assertEquals($expectedBreakeven, $result);
        $this->assertGreaterThan(374000, $result);
    }

    // ------------------------------------------------------------------
    // calculateRentalAnalysis
    // ------------------------------------------------------------------

    public function testCalculateRentalAnalysis(): void
    {
        $result = $this->service->calculateRentalAnalysis(
            arv: 500000,
            monthlyRent: 3000,
            totalInvestment: 400000,
        );

        $this->assertArrayHasKey('monthly_rent', $result);
        $this->assertArrayHasKey('annual_gross', $result);
        $this->assertArrayHasKey('vacancy_loss', $result);
        $this->assertArrayHasKey('operating_expenses', $result);
        $this->assertArrayHasKey('noi', $result);
        $this->assertArrayHasKey('cap_rate', $result);
        $this->assertArrayHasKey('cash_on_cash', $result);
        $this->assertArrayHasKey('grm', $result);

        $this->assertEquals(3000.0, $result['monthly_rent']);
        $this->assertEquals(36000.0, $result['annual_gross']);

        // vacancy_loss = 36000 * 0.05 = 1800
        $this->assertEquals(1800.0, $result['vacancy_loss']);

        // NOI = annual_gross - operating_expenses
        $this->assertEquals(
            round($result['annual_gross'] - $result['operating_expenses'], 2),
            $result['noi']
        );

        // cap_rate = (noi / arv) * 100
        $expectedCapRate = round(($result['noi'] / 500000) * 100, 2);
        $this->assertEquals($expectedCapRate, $result['cap_rate']);

        // cash_on_cash = (noi / totalInvestment) * 100
        $expectedCashOnCash = round(($result['noi'] / 400000) * 100, 2);
        $this->assertEquals($expectedCashOnCash, $result['cash_on_cash']);
    }

    public function testCalculateRentalAnalysisDepreciation(): void
    {
        $result = $this->service->calculateRentalAnalysis(
            arv: 500000,
            monthlyRent: 3000,
            totalInvestment: 400000,
        );

        $this->assertArrayHasKey('annual_depreciation', $result);
        $this->assertArrayHasKey('tax_shelter', $result);

        // depreciable = 500000 * (1 - 0.20) = 400000
        // annual_depreciation = 400000 / 27.5
        $expectedDepreciation = round(400000 / 27.5, 2);
        $this->assertEquals($expectedDepreciation, $result['annual_depreciation']);

        // tax_shelter = annual_depreciation * 0.32
        $this->assertEqualsWithDelta($result['annual_depreciation'] * 0.32, $result['tax_shelter'], 0.02);
    }

    // ------------------------------------------------------------------
    // calculateBrrrr
    // ------------------------------------------------------------------

    public function testCalculateBrrrr(): void
    {
        $result = $this->service->calculateBrrrr(
            arv: 500000,
            noi: 20000,
            totalCashIn: 400000,
        );

        $this->assertArrayHasKey('refi_loan', $result);
        $this->assertArrayHasKey('monthly_payment', $result);
        $this->assertArrayHasKey('annual_debt_service', $result);
        $this->assertArrayHasKey('post_refi_cash_flow', $result);
        $this->assertArrayHasKey('dscr', $result);
        $this->assertArrayHasKey('cash_left', $result);

        // refi_loan = 500000 * 0.75 = 375000
        $this->assertEquals(375000.0, $result['refi_loan']);

        // cash_left = 400000 - 375000 = 25000
        $this->assertEquals(25000.0, $result['cash_left']);

        // monthly_payment should be positive.
        $this->assertGreaterThan(0, $result['monthly_payment']);

        // annual_debt_service = monthly_payment * 12
        $this->assertEqualsWithDelta(
            $result['monthly_payment'] * 12,
            $result['annual_debt_service'],
            0.10
        );
    }

    public function testCalculateBrrrrDscr(): void
    {
        $result = $this->service->calculateBrrrr(
            arv: 500000,
            noi: 30000,
            totalCashIn: 400000,
        );

        // dscr = noi / annual_debt_service
        $expectedDscr = round($result['annual_debt_service'] > 0
            ? 30000 / $result['annual_debt_service']
            : 0.0, 2);
        $this->assertEquals($expectedDscr, $result['dscr']);
    }

    // ------------------------------------------------------------------
    // estimateMonthlyRent
    // ------------------------------------------------------------------

    public function testEstimateMonthlyRent(): void
    {
        $property = ['living_area' => 2000];

        $result = $this->service->estimateMonthlyRent($property);

        // 2000 * 1.80 = 3600
        $this->assertEquals(3600.0, $result);
    }

    public function testEstimateMonthlyRentNoArea(): void
    {
        $property = ['living_area' => 0];

        $result = $this->service->estimateMonthlyRent($property);

        $this->assertEquals(0.0, $result);
    }

    // ------------------------------------------------------------------
    // gradeRisk
    // ------------------------------------------------------------------

    public function testGradeRisk(): void
    {
        // Excellent deal: high confidence, wide margin, tight CV, fast market, many comps.
        $result = $this->service->gradeRisk(
            arvConfidence: 80.0,
            breakevenArv: 300000.0,
            arv: 500000.0,
            priceVarianceCv: 0.08,
            avgDom: 20,
            compCount: 10,
        );

        $this->assertArrayHasKey('grade', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('factors', $result);

        // All factors should be high -> grade A.
        $this->assertEquals('A', $result['grade']);
        $this->assertGreaterThanOrEqual(80, $result['score']);

        // Verify all factor keys.
        $this->assertArrayHasKey('arv_confidence', $result['factors']);
        $this->assertArrayHasKey('margin_cushion', $result['factors']);
        $this->assertArrayHasKey('comp_consistency', $result['factors']);
        $this->assertArrayHasKey('market_velocity', $result['factors']);
        $this->assertArrayHasKey('comp_count_factor', $result['factors']);

        // Poor deal: low confidence, negative margin, high CV, slow market, few comps.
        $resultF = $this->service->gradeRisk(
            arvConfidence: 10.0,
            breakevenArv: 550000.0,
            arv: 400000.0,
            priceVarianceCv: 0.40,
            avgDom: 120,
            compCount: 1,
        );

        $this->assertEquals('F', $resultF['grade']);
        $this->assertLessThan(35, $resultF['score']);

        // Medium deal -> B or C.
        $resultMid = $this->service->gradeRisk(
            arvConfidence: 55.0,
            breakevenArv: 350000.0,
            arv: 500000.0,
            priceVarianceCv: 0.15,
            avgDom: 45,
            compCount: 5,
        );

        $this->assertContains($resultMid['grade'], ['B', 'C']);

        // D grade.
        $resultD = $this->service->gradeRisk(
            arvConfidence: 25.0,
            breakevenArv: 400000.0,
            arv: 420000.0,
            priceVarianceCv: 0.25,
            avgDom: 70,
            compCount: 2,
        );

        $this->assertContains($resultD['grade'], ['C', 'D']);
    }
}

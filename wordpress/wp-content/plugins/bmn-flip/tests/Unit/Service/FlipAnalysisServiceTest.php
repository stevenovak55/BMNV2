<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Service;

use BMN\Flip\Repository\FlipAnalysisRepository;
use BMN\Flip\Repository\FlipComparableRepository;
use BMN\Flip\Service\ArvService;
use BMN\Flip\Service\FinancialService;
use BMN\Flip\Service\FlipAnalysisService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FlipAnalysisServiceTest extends TestCase
{
    private FlipAnalysisService $service;
    private ArvService&MockObject $arvService;
    private FinancialService&MockObject $financialService;
    private FlipAnalysisRepository&MockObject $analysisRepo;
    private FlipComparableRepository&MockObject $comparableRepo;

    protected function setUp(): void
    {
        $this->arvService = $this->createMock(ArvService::class);
        $this->financialService = $this->createMock(FinancialService::class);
        $this->analysisRepo = $this->createMock(FlipAnalysisRepository::class);
        $this->comparableRepo = $this->createMock(FlipComparableRepository::class);

        $this->service = new FlipAnalysisService(
            $this->arvService,
            $this->financialService,
            $this->analysisRepo,
            $this->comparableRepo,
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function stubAllFinancialMethods(): void
    {
        $this->arvService->method('calculateArv')->willReturn([
            'arv'                  => 500000.0,
            'confidence'           => 'high',
            'confidence_score'     => 80.0,
            'comp_count'           => 5,
            'avg_ppsf'             => 250.0,
            'comparables'          => [],
            'neighborhood_ceiling' => 600000.0,
        ]);

        $this->financialService->method('estimateRehabCost')->willReturn([
            'total'            => 50000.0,
            'per_sqft'         => 25.0,
            'contingency_rate' => 0.12,
            'lead_paint'       => 0.0,
            'base_cost'        => 44000.0,
        ]);

        $this->financialService->method('estimateHoldPeriod')->willReturn(4);

        $this->financialService->method('calculateTransactionCosts')->willReturn([
            'purchase_closing'  => 7824.0,
            'sale_costs'        => 32780.0,
            'transfer_tax_buy'  => 1824.0,
            'transfer_tax_sell' => 2280.0,
        ]);

        $this->financialService->method('calculateHoldingCosts')->willReturn([
            'monthly_tax'       => 433.33,
            'monthly_insurance' => 166.67,
            'monthly_utilities' => 350.0,
            'total'             => 3800.0,
        ]);

        $this->financialService->method('calculateCashScenario')->willReturn([
            'profit'     => 105596.0,
            'roi'        => 28.83,
            'investment' => 361624.0,
        ]);

        $this->financialService->method('calculateFinancedScenario')->willReturn([
            'profit'           => 82596.0,
            'cash_on_cash_roi' => 45.2,
            'cash_invested'    => 177824.0,
            'loan_amount'      => 320000.0,
            'financing_costs'  => 17600.0,
            'annualized_roi'   => 180.5,
        ]);

        $this->financialService->method('calculateMao')->willReturn([
            'classic'  => 300000.0,
            'adjusted' => 278600.0,
        ]);

        $this->financialService->method('calculateBreakevenArv')->willReturn(420000.0);

        $this->financialService->method('estimateMonthlyRent')->willReturn(3600.0);

        $this->financialService->method('calculateRentalAnalysis')->willReturn([
            'monthly_rent'        => 3600.0,
            'annual_gross'        => 43200.0,
            'vacancy_loss'        => 2160.0,
            'operating_expenses'  => 15000.0,
            'noi'                 => 28200.0,
            'cap_rate'            => 5.64,
            'cash_on_cash'        => 7.8,
            'grm'                 => 8.37,
            'annual_depreciation' => 14545.45,
            'tax_shelter'         => 4654.55,
        ]);

        $this->financialService->method('calculateBrrrr')->willReturn([
            'refi_loan'           => 375000.0,
            'monthly_payment'     => 2546.38,
            'annual_debt_service' => 30556.56,
            'post_refi_cash_flow' => -2356.56,
            'dscr'                => 0.92,
            'cash_left'           => -13376.0,
        ]);

        $this->financialService->method('gradeRisk')->willReturn([
            'grade'   => 'B',
            'score'   => 68.5,
            'factors' => [
                'arv_confidence'     => 100,
                'margin_cushion'     => 60,
                'comp_consistency'   => 70,
                'market_velocity'    => 40,
                'comp_count_factor'  => 80,
            ],
        ]);
    }

    private function buildSubjectProperty(array $overrides = []): array
    {
        return array_merge([
            'listing_id'          => 'MLS123',
            'address'             => '100 Main St',
            'city'                => 'Boston',
            'state'               => 'MA',
            'zip'                 => '02101',
            'list_price'          => 400000,
            'property_type'       => 'Single Family Residence',
            'bedrooms_total'      => 3,
            'bathrooms_total'     => 2,
            'living_area'         => 2000,
            'lot_size_acres'      => 0.25,
            'year_built'          => 1990,
            'garage_spaces'       => 1,
            'latitude'            => 42.36,
            'longitude'           => -71.06,
            'days_on_market'      => 30,
            'original_list_price' => 420000,
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // analyzeProperty
    // ------------------------------------------------------------------

    public function testAnalyzePropertyReturnsResult(): void
    {
        $this->stubAllFinancialMethods();
        $this->analysisRepo->method('create')->willReturn(1);

        $result = $this->service->analyzeProperty($this->buildSubjectProperty());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('listing_id', $result);
        $this->assertEquals('MLS123', $result['listing_id']);
        $this->assertArrayHasKey('estimated_arv', $result);
        $this->assertEquals(500000.0, $result['estimated_arv']);
        $this->assertArrayHasKey('arv_confidence', $result);
        $this->assertEquals('high', $result['arv_confidence']);
        $this->assertArrayHasKey('cash_profit', $result);
        $this->assertArrayHasKey('cash_roi', $result);
        $this->assertArrayHasKey('mao_classic', $result);
        $this->assertArrayHasKey('mao_adjusted', $result);
        $this->assertArrayHasKey('best_strategy', $result);
        $this->assertArrayHasKey('flip_viable', $result);
        $this->assertArrayHasKey('rental_viable', $result);
        $this->assertArrayHasKey('brrrr_viable', $result);
        $this->assertArrayHasKey('deal_risk_grade', $result);
        $this->assertArrayHasKey('total_score', $result);
        $this->assertArrayHasKey('rental_analysis', $result);
        $this->assertArrayHasKey('brrrr_analysis', $result);
        $this->assertArrayHasKey('risk_factors', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(1, $result['id']);
    }

    public function testAnalyzePropertySavesToDb(): void
    {
        $this->stubAllFinancialMethods();

        $this->analysisRepo->expects($this->once())
            ->method('create')
            ->willReturn(42);

        $result = $this->service->analyzeProperty($this->buildSubjectProperty());

        $this->assertEquals(42, $result['id']);
    }

    public function testAnalyzePropertySavesComps(): void
    {
        $this->arvService->method('calculateArv')->willReturn([
            'arv'                  => 500000.0,
            'confidence'           => 'high',
            'confidence_score'     => 80.0,
            'comp_count'           => 2,
            'avg_ppsf'             => 250.0,
            'comparables'          => [
                [
                    'comp' => (object) [
                        'listing_id' => 'C1', 'address' => '1 Comp St', 'city' => 'Boston',
                        'close_price' => 490000, 'close_date' => '2026-01-15',
                        'property_type' => 'SFR', 'bedrooms_total' => 3,
                        'bathrooms_total' => 2, 'living_area' => 2000,
                        'year_built' => 1990, 'lot_size_acres' => 0.25,
                        'garage_spaces' => 1, 'days_on_market' => 20,
                        'distance_miles' => 0.3, 'remarks' => '',
                    ],
                    'adjustments' => ['bedroom' => 0, 'sqft' => 0],
                    'total_adj' => 0,
                    'adjusted_price' => 490000,
                    'weight' => 5.0,
                ],
                [
                    'comp' => (object) [
                        'listing_id' => 'C2', 'address' => '2 Comp St', 'city' => 'Boston',
                        'close_price' => 510000, 'close_date' => '2026-01-10',
                        'property_type' => 'SFR', 'bedrooms_total' => 3,
                        'bathrooms_total' => 2, 'living_area' => 2100,
                        'year_built' => 1992, 'lot_size_acres' => 0.28,
                        'garage_spaces' => 1, 'days_on_market' => 25,
                        'distance_miles' => 0.5, 'remarks' => 'renovated kitchen',
                    ],
                    'adjustments' => ['sqft' => -2500],
                    'total_adj' => -2500,
                    'adjusted_price' => 507500,
                    'weight' => 4.5,
                ],
            ],
            'neighborhood_ceiling' => 600000.0,
        ]);

        // Stub all financial methods.
        $this->financialService->method('estimateRehabCost')->willReturn([
            'total' => 50000.0, 'per_sqft' => 25.0, 'contingency_rate' => 0.12,
            'lead_paint' => 0.0, 'base_cost' => 44000.0,
        ]);
        $this->financialService->method('estimateHoldPeriod')->willReturn(4);
        $this->financialService->method('calculateTransactionCosts')->willReturn([
            'purchase_closing' => 7824.0, 'sale_costs' => 32780.0,
            'transfer_tax_buy' => 1824.0, 'transfer_tax_sell' => 2280.0,
        ]);
        $this->financialService->method('calculateHoldingCosts')->willReturn([
            'monthly_tax' => 433.33, 'monthly_insurance' => 166.67,
            'monthly_utilities' => 350.0, 'total' => 3800.0,
        ]);
        $this->financialService->method('calculateCashScenario')->willReturn([
            'profit' => 105596.0, 'roi' => 28.83, 'investment' => 361624.0,
        ]);
        $this->financialService->method('calculateFinancedScenario')->willReturn([
            'profit' => 82596.0, 'cash_on_cash_roi' => 45.2, 'cash_invested' => 177824.0,
            'loan_amount' => 320000.0, 'financing_costs' => 17600.0, 'annualized_roi' => 180.5,
        ]);
        $this->financialService->method('calculateMao')->willReturn([
            'classic' => 300000.0, 'adjusted' => 278600.0,
        ]);
        $this->financialService->method('calculateBreakevenArv')->willReturn(420000.0);
        $this->financialService->method('estimateMonthlyRent')->willReturn(3600.0);
        $this->financialService->method('calculateRentalAnalysis')->willReturn([
            'monthly_rent' => 3600.0, 'annual_gross' => 43200.0, 'vacancy_loss' => 2160.0,
            'operating_expenses' => 15000.0, 'noi' => 28200.0, 'cap_rate' => 5.64,
            'cash_on_cash' => 7.8, 'grm' => 8.37, 'annual_depreciation' => 14545.45,
            'tax_shelter' => 4654.55,
        ]);
        $this->financialService->method('calculateBrrrr')->willReturn([
            'refi_loan' => 375000.0, 'monthly_payment' => 2546.38,
            'annual_debt_service' => 30556.56, 'post_refi_cash_flow' => -2356.56,
            'dscr' => 0.92, 'cash_left' => -13376.0,
        ]);
        $this->financialService->method('gradeRisk')->willReturn([
            'grade' => 'B', 'score' => 68.5, 'factors' => [],
        ]);

        $this->analysisRepo->method('create')->willReturn(99);

        // Expect comparableRepo->create to be called exactly 2 times (one per comp).
        $this->comparableRepo->expects($this->exactly(2))
            ->method('create');

        $this->service->analyzeProperty($this->buildSubjectProperty());
    }

    public function testAnalyzePropertyWithReportId(): void
    {
        $this->stubAllFinancialMethods();
        $this->analysisRepo->method('create')->willReturn(5);

        $result = $this->service->analyzeProperty($this->buildSubjectProperty(), 42);

        $this->assertEquals(42, $result['report_id']);
    }

    // ------------------------------------------------------------------
    // getAnalysis
    // ------------------------------------------------------------------

    public function testGetAnalysisFound(): void
    {
        $analysis = (object) [
            'id' => 1, 'listing_id' => 'MLS123', 'estimated_arv' => 500000,
        ];
        $comps = [
            (object) ['id' => 10, 'analysis_id' => 1, 'listing_id' => 'C1'],
        ];

        $this->analysisRepo->method('find')->willReturn($analysis);
        $this->comparableRepo->method('findByAnalysis')->willReturn($comps);

        $result = $this->service->getAnalysis(1);

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertEquals('MLS123', $result['listing_id']);
        $this->assertArrayHasKey('comparables', $result);
        $this->assertCount(1, $result['comparables']);
    }

    public function testGetAnalysisNotFound(): void
    {
        $this->analysisRepo->method('find')->willReturn(null);

        $result = $this->service->getAnalysis(999);

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // getAnalysesByReport
    // ------------------------------------------------------------------

    public function testGetAnalysesByReport(): void
    {
        $analyses = [
            (object) ['id' => 1, 'report_id' => 10],
            (object) ['id' => 2, 'report_id' => 10],
        ];

        $this->analysisRepo->method('findByReport')
            ->with(10, 50, 0)
            ->willReturn($analyses);

        $this->analysisRepo->method('countByReport')
            ->with(10)
            ->willReturn(2);

        $result = $this->service->getAnalysesByReport(10, 1, 50);

        $this->assertArrayHasKey('analyses', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(2, $result['analyses']);
        $this->assertEquals(2, $result['total']);
    }

    // ------------------------------------------------------------------
    // getReportSummary
    // ------------------------------------------------------------------

    public function testGetReportSummary(): void
    {
        $summary = [
            (object) ['city' => 'Boston', 'total' => 10, 'viable' => 3, 'avg_score' => 55.2, 'avg_roi' => 22.5],
        ];

        $this->analysisRepo->method('getReportSummary')
            ->with(10)
            ->willReturn($summary);

        $result = $this->service->getReportSummary(10);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // deleteAnalysesByReport
    // ------------------------------------------------------------------

    public function testDeleteAnalysesByReport(): void
    {
        // Comps should be deleted first, then analyses.
        $this->comparableRepo->expects($this->once())
            ->method('deleteByReport')
            ->with(10);

        $this->analysisRepo->expects($this->once())
            ->method('deleteByReport')
            ->with(10)
            ->willReturn(true);

        $result = $this->service->deleteAnalysesByReport(10);

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // Disqualification (tested indirectly via analyzeProperty)
    // ------------------------------------------------------------------

    public function testDisqualificationLowPrice(): void
    {
        $this->stubAllFinancialMethods();
        $this->analysisRepo->method('create')->willReturn(1);

        $property = $this->buildSubjectProperty(['list_price' => 50000]);
        $result = $this->service->analyzeProperty($property);

        $this->assertEquals('List price below $100K minimum', $result['dq_reason']);
        $this->assertTrue($result['disqualified']);
    }

    public function testDisqualificationNoComps(): void
    {
        $this->arvService->method('calculateArv')->willReturn([
            'arv'                  => 0.0,
            'confidence'           => 'none',
            'confidence_score'     => 0.0,
            'comp_count'           => 0,
            'avg_ppsf'             => 0.0,
            'comparables'          => [],
            'neighborhood_ceiling' => null,
        ]);

        $this->financialService->method('estimateRehabCost')->willReturn([
            'total' => 50000.0, 'per_sqft' => 25.0, 'contingency_rate' => 0.12,
            'lead_paint' => 0.0, 'base_cost' => 44000.0,
        ]);
        $this->financialService->method('estimateHoldPeriod')->willReturn(4);
        $this->financialService->method('calculateTransactionCosts')->willReturn([
            'purchase_closing' => 7824.0, 'sale_costs' => 0.0,
            'transfer_tax_buy' => 1824.0, 'transfer_tax_sell' => 0.0,
        ]);
        $this->financialService->method('calculateHoldingCosts')->willReturn([
            'monthly_tax' => 433.33, 'monthly_insurance' => 166.67,
            'monthly_utilities' => 350.0, 'total' => 3800.0,
        ]);
        $this->financialService->method('calculateCashScenario')->willReturn([
            'profit' => -500000.0, 'roi' => -100.0, 'investment' => 461624.0,
        ]);
        $this->financialService->method('calculateFinancedScenario')->willReturn([
            'profit' => -500000.0, 'cash_on_cash_roi' => -100.0, 'cash_invested' => 177824.0,
            'loan_amount' => 320000.0, 'financing_costs' => 17600.0, 'annualized_roi' => -100.0,
        ]);
        $this->financialService->method('calculateMao')->willReturn([
            'classic' => -50000.0, 'adjusted' => -71400.0,
        ]);
        $this->financialService->method('calculateBreakevenArv')->willReturn(500000.0);
        $this->financialService->method('estimateMonthlyRent')->willReturn(3600.0);
        $this->financialService->method('calculateRentalAnalysis')->willReturn([
            'monthly_rent' => 3600.0, 'annual_gross' => 43200.0, 'vacancy_loss' => 2160.0,
            'operating_expenses' => 15000.0, 'noi' => 28200.0, 'cap_rate' => 0.0,
            'cash_on_cash' => 0.0, 'grm' => 0.0, 'annual_depreciation' => 0.0,
            'tax_shelter' => 0.0,
        ]);
        $this->financialService->method('calculateBrrrr')->willReturn([
            'refi_loan' => 0.0, 'monthly_payment' => 0.0,
            'annual_debt_service' => 0.0, 'post_refi_cash_flow' => 0.0,
            'dscr' => 0.0, 'cash_left' => 0.0,
        ]);
        $this->financialService->method('gradeRisk')->willReturn([
            'grade' => 'F', 'score' => 10.0, 'factors' => [],
        ]);

        $this->analysisRepo->method('create')->willReturn(1);

        $result = $this->service->analyzeProperty($this->buildSubjectProperty());

        $this->assertEquals('No comparable sales found', $result['dq_reason']);
    }

    public function testDisqualificationSmallArea(): void
    {
        $this->stubAllFinancialMethods();
        $this->analysisRepo->method('create')->willReturn(1);

        $property = $this->buildSubjectProperty(['living_area' => 400]);
        $result = $this->service->analyzeProperty($property);

        $this->assertEquals('Living area below 600 sqft minimum', $result['dq_reason']);
    }
}

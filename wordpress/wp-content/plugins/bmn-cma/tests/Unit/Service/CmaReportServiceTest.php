<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Service;

use BMN\CMA\Repository\CmaReportRepository;
use BMN\CMA\Repository\ComparableRepository;
use BMN\CMA\Repository\ValueHistoryRepository;
use BMN\CMA\Service\AdjustmentService;
use BMN\CMA\Service\CmaReportService;
use BMN\CMA\Service\ComparableSearchService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CmaReportServiceTest extends TestCase
{
    private CmaReportRepository&MockObject $reportRepo;
    private ComparableRepository&MockObject $comparableRepo;
    private ValueHistoryRepository&MockObject $historyRepo;
    private ComparableSearchService&MockObject $searchService;
    private AdjustmentService $adjustmentService;
    private CmaReportService $service;

    protected function setUp(): void
    {
        $this->reportRepo = $this->createMock(CmaReportRepository::class);
        $this->comparableRepo = $this->createMock(ComparableRepository::class);
        $this->historyRepo = $this->createMock(ValueHistoryRepository::class);
        $this->searchService = $this->createMock(ComparableSearchService::class);
        $this->adjustmentService = new AdjustmentService();

        $this->service = new CmaReportService(
            $this->reportRepo,
            $this->comparableRepo,
            $this->historyRepo,
            $this->searchService,
            $this->adjustmentService,
        );
    }

    // ------------------------------------------------------------------
    // generateReport
    // ------------------------------------------------------------------

    public function testGenerateReportReturnsFullResult(): void
    {
        $comp = (object) [
            'listing_id'      => 'MLS200',
            'close_price'     => 500000,
            'bedrooms_total'  => 3,
            'bathrooms_total' => 2,
            'living_area'     => 2000,
            'year_built'      => 2010,
            'garage_spaces'   => 2,
            'lot_size_acres'  => 0.5,
            'distance_miles'  => 0.5,
            'address'         => '456 Elm St',
            'city'            => 'Boston',
            'state'           => 'MA',
            'zip'             => '02115',
            'property_type'   => 'Single Family',
            'close_date'      => '2025-06-01',
            'days_on_market'  => 30,
            'latitude'        => 42.36,
            'longitude'       => -71.05,
        ];

        $this->searchService->method('findComparables')->willReturn([$comp]);
        $this->searchService->method('expandSearch')->willReturn([$comp]);

        $this->reportRepo->method('create')->willReturn(42);
        $this->comparableRepo->method('upsert')->willReturn(true);
        $this->historyRepo->method('create')->willReturn(1);

        $subjectData = [
            'listing_id'      => 'MLS100',
            'address'         => '123 Main St',
            'city'            => 'Boston',
            'state'           => 'MA',
            'zip'             => '02115',
            'latitude'        => 42.36,
            'longitude'       => -71.05,
            'bedrooms_total'  => 3,
            'bathrooms_total' => 2,
            'living_area'     => 2000,
            'year_built'      => 2010,
            'lot_size_acres'  => 0.5,
            'garage_spaces'   => 2,
        ];

        $result = $this->service->generateReport($subjectData, [], 1);

        $this->assertSame(42, $result['report_id']);
        $this->assertArrayHasKey('comparables', $result);
        $this->assertArrayHasKey('valuation', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('statistics', $result);
        $this->assertCount(1, $result['comparables']);
    }

    public function testGenerateReportExpandsSearchWhenInsufficientComps(): void
    {
        $this->searchService->method('findComparables')->willReturn([]);
        $this->searchService->expects($this->once())->method('expandSearch')->willReturn([]);

        $this->reportRepo->method('create')->willReturn(1);
        $this->historyRepo->method('create')->willReturn(1);

        $subjectData = [
            'listing_id' => 'MLS100',
            'latitude'   => 42.36,
            'longitude'  => -71.05,
        ];

        $result = $this->service->generateReport($subjectData, [], 1);

        $this->assertSame(1, $result['report_id']);
        $this->assertCount(0, $result['comparables']);
    }

    public function testGenerateReportThrowsOnCreateFailure(): void
    {
        $this->searchService->method('findComparables')->willReturn([]);
        $this->reportRepo->method('create')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create CMA report.');

        $this->service->generateReport(['listing_id' => 'MLS100'], [], 1);
    }

    // ------------------------------------------------------------------
    // getReport
    // ------------------------------------------------------------------

    public function testGetReportReturnsDataWhenOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $comps = [(object) ['id' => 1, 'report_id' => 1]];

        $this->reportRepo->method('find')->willReturn($report);
        $this->comparableRepo->method('findByReport')->willReturn($comps);

        $result = $this->service->getReport(1, 42);

        $this->assertNotNull($result);
        $this->assertSame($report, $result['report']);
        $this->assertSame($comps, $result['comparables']);
    }

    public function testGetReportReturnsNullWhenNotOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $this->reportRepo->method('find')->willReturn($report);

        $result = $this->service->getReport(1, 99);

        $this->assertNull($result);
    }

    public function testGetReportReturnsNullWhenNotFound(): void
    {
        $this->reportRepo->method('find')->willReturn(null);

        $result = $this->service->getReport(999, 1);

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // getUserReports
    // ------------------------------------------------------------------

    public function testGetUserReportsReturnsPaginatedData(): void
    {
        $reports = [(object) ['id' => 1], (object) ['id' => 2]];
        $this->reportRepo->method('findByUser')->willReturn($reports);
        $this->reportRepo->method('countByUser')->willReturn(50);

        $result = $this->service->getUserReports(42, 1, 20);

        $this->assertSame($reports, $result['reports']);
        $this->assertSame(50, $result['total']);
    }

    public function testGetUserReportsCalculatesCorrectOffset(): void
    {
        $this->reportRepo->expects($this->once())
            ->method('findByUser')
            ->with(42, 10, 20); // page 3, perPage 10 => offset 20.
        $this->reportRepo->method('countByUser')->willReturn(100);

        $this->service->getUserReports(42, 3, 10);
    }

    // ------------------------------------------------------------------
    // updateReport
    // ------------------------------------------------------------------

    public function testUpdateReportSucceedsForOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $this->reportRepo->method('find')->willReturn($report);
        $this->reportRepo->method('update')->willReturn(true);

        $result = $this->service->updateReport(1, 42, ['session_name' => 'Updated']);

        $this->assertTrue($result);
    }

    public function testUpdateReportFailsForNonOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $this->reportRepo->method('find')->willReturn($report);

        $result = $this->service->updateReport(1, 99, ['session_name' => 'Updated']);

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // deleteReport
    // ------------------------------------------------------------------

    public function testDeleteReportSucceedsForOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $this->reportRepo->method('find')->willReturn($report);
        $this->comparableRepo->expects($this->once())->method('deleteByReport')->with(1);
        $this->reportRepo->method('delete')->willReturn(true);

        $result = $this->service->deleteReport(1, 42);

        $this->assertTrue($result);
    }

    public function testDeleteReportFailsForNonOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $this->reportRepo->method('find')->willReturn($report);

        $result = $this->service->deleteReport(1, 99);

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // toggleFavorite
    // ------------------------------------------------------------------

    public function testToggleFavoriteSucceedsForOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $this->reportRepo->method('find')->willReturn($report);
        $this->reportRepo->method('toggleFavorite')->willReturn(true);

        $result = $this->service->toggleFavorite(1, 42);

        $this->assertTrue($result);
    }

    public function testToggleFavoriteFailsForNonOwner(): void
    {
        $report = (object) ['id' => 1, 'user_id' => 42];
        $this->reportRepo->method('find')->willReturn($report);

        $result = $this->service->toggleFavorite(1, 99);

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // getPropertyHistory / getValueTrends
    // ------------------------------------------------------------------

    public function testGetPropertyHistoryDelegatesToRepo(): void
    {
        $history = [(object) ['id' => 1]];
        $this->historyRepo->method('findByListing')->with('MLS100')->willReturn($history);

        $result = $this->service->getPropertyHistory('MLS100');

        $this->assertSame($history, $result);
    }

    public function testGetValueTrendsDelegatesToRepo(): void
    {
        $trends = [(object) ['id' => 1]];
        $this->historyRepo->method('getTrends')->with('MLS100')->willReturn($trends);

        $result = $this->service->getValueTrends('MLS100');

        $this->assertSame($trends, $result);
    }
}

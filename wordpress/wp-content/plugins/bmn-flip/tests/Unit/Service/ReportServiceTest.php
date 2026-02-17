<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Service;

use BMN\Flip\Repository\FlipAnalysisRepository;
use BMN\Flip\Repository\FlipComparableRepository;
use BMN\Flip\Repository\FlipReportRepository;
use BMN\Flip\Repository\MonitorSeenRepository;
use BMN\Flip\Service\ReportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase
{
    private ReportService $service;
    private FlipReportRepository&MockObject $reportRepo;
    private FlipAnalysisRepository&MockObject $analysisRepo;
    private FlipComparableRepository&MockObject $comparableRepo;
    private MonitorSeenRepository&MockObject $monitorSeenRepo;

    protected function setUp(): void
    {
        $this->reportRepo = $this->createMock(FlipReportRepository::class);
        $this->analysisRepo = $this->createMock(FlipAnalysisRepository::class);
        $this->comparableRepo = $this->createMock(FlipComparableRepository::class);
        $this->monitorSeenRepo = $this->createMock(MonitorSeenRepository::class);

        $this->service = new ReportService(
            $this->reportRepo,
            $this->analysisRepo,
            $this->comparableRepo,
            $this->monitorSeenRepo,
        );
    }

    // ------------------------------------------------------------------
    // createReport
    // ------------------------------------------------------------------

    public function testCreateReport(): void
    {
        $this->reportRepo->expects($this->once())
            ->method('create')
            ->with([
                'user_id' => 1,
                'name'    => 'Boston Flip Report',
                'type'    => 'manual',
                'status'  => 'active',
                'cities'  => ['Boston', 'Cambridge'],
                'filters' => ['min_price' => 200000],
            ])
            ->willReturn(42);

        $result = $this->service->createReport(
            userId: 1,
            name: 'Boston Flip Report',
            cities: ['Boston', 'Cambridge'],
            filters: ['min_price' => 200000],
        );

        $this->assertEquals(42, $result);
    }

    // ------------------------------------------------------------------
    // getReport
    // ------------------------------------------------------------------

    public function testGetReportFound(): void
    {
        $report = (object) [
            'id'      => 10,
            'user_id' => 1,
            'name'    => 'My Report',
            'status'  => 'active',
        ];

        $summary = [
            (object) ['city' => 'Boston', 'total' => 5, 'viable' => 2],
        ];

        $this->reportRepo->method('find')
            ->with(10)
            ->willReturn($report);

        $this->analysisRepo->method('getReportSummary')
            ->with(10)
            ->willReturn($summary);

        $result = $this->service->getReport(10, 1);

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertEquals(10, $result['id']);
        $this->assertEquals('My Report', $result['name']);
        $this->assertArrayHasKey('summary', $result);
        $this->assertCount(1, $result['summary']);
    }

    public function testGetReportNotFound(): void
    {
        $this->reportRepo->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->getReport(999, 1);

        $this->assertNull($result);
    }

    public function testGetReportWrongUser(): void
    {
        $report = (object) [
            'id'      => 10,
            'user_id' => 1,
            'name'    => 'My Report',
        ];

        $this->reportRepo->method('find')
            ->with(10)
            ->willReturn($report);

        // User ID 99 does not own report with user_id 1.
        $result = $this->service->getReport(10, 99);

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // getUserReports
    // ------------------------------------------------------------------

    public function testGetUserReports(): void
    {
        $reports = [
            (object) ['id' => 1, 'name' => 'Report A'],
            (object) ['id' => 2, 'name' => 'Report B'],
        ];

        $this->reportRepo->method('findByUser')
            ->with(1, 20, 0)
            ->willReturn($reports);

        $this->reportRepo->method('countByUser')
            ->with(1)
            ->willReturn(2);

        $result = $this->service->getUserReports(1, 1, 20);

        $this->assertArrayHasKey('reports', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(2, $result['reports']);
        $this->assertEquals(2, $result['total']);
    }

    // ------------------------------------------------------------------
    // updateReport
    // ------------------------------------------------------------------

    public function testUpdateReport(): void
    {
        $report = (object) [
            'id'      => 10,
            'user_id' => 1,
            'name'    => 'Old Name',
        ];

        $this->reportRepo->method('find')
            ->with(10)
            ->willReturn($report);

        $this->reportRepo->expects($this->once())
            ->method('update')
            ->with(10, ['name' => 'New Name'])
            ->willReturn(true);

        $result = $this->service->updateReport(10, 1, ['name' => 'New Name']);

        $this->assertTrue($result);
    }

    public function testUpdateReportNotOwned(): void
    {
        $report = (object) [
            'id'      => 10,
            'user_id' => 1,
            'name'    => 'My Report',
        ];

        $this->reportRepo->method('find')
            ->with(10)
            ->willReturn($report);

        // User 99 does not own this report.
        $result = $this->service->updateReport(10, 99, ['name' => 'Hacked']);

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // deleteReport
    // ------------------------------------------------------------------

    public function testDeleteReport(): void
    {
        $report = (object) [
            'id'      => 10,
            'user_id' => 1,
        ];

        $this->reportRepo->method('find')
            ->with(10)
            ->willReturn($report);

        // Verify cascade delete order: comps -> analyses -> monitor_seen -> report.
        $deleteOrder = [];

        $this->comparableRepo->expects($this->once())
            ->method('deleteByReport')
            ->with(10)
            ->willReturnCallback(function () use (&$deleteOrder) {
                $deleteOrder[] = 'comps';
                return true;
            });

        $this->analysisRepo->expects($this->once())
            ->method('deleteByReport')
            ->with(10)
            ->willReturnCallback(function () use (&$deleteOrder) {
                $deleteOrder[] = 'analyses';
                return true;
            });

        $this->monitorSeenRepo->expects($this->once())
            ->method('deleteByReport')
            ->with(10)
            ->willReturnCallback(function () use (&$deleteOrder) {
                $deleteOrder[] = 'monitor_seen';
                return true;
            });

        $this->reportRepo->expects($this->once())
            ->method('delete')
            ->with(10)
            ->willReturnCallback(function () use (&$deleteOrder) {
                $deleteOrder[] = 'report';
                return true;
            });

        $result = $this->service->deleteReport(10, 1);

        $this->assertTrue($result);
        $this->assertEquals(['comps', 'analyses', 'monitor_seen', 'report'], $deleteOrder);
    }

    // ------------------------------------------------------------------
    // toggleFavorite
    // ------------------------------------------------------------------

    public function testToggleFavorite(): void
    {
        $report = (object) [
            'id'      => 10,
            'user_id' => 1,
        ];

        $this->reportRepo->method('find')
            ->with(10)
            ->willReturn($report);

        $this->reportRepo->expects($this->once())
            ->method('toggleFavorite')
            ->with(10)
            ->willReturn(true);

        $result = $this->service->toggleFavorite(10, 1);

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // recordRun
    // ------------------------------------------------------------------

    public function testRecordRun(): void
    {
        $this->reportRepo->expects($this->once())
            ->method('incrementRunCount')
            ->with(10, 150, 25)
            ->willReturn(true);

        $result = $this->service->recordRun(10, 150, 25);

        $this->assertTrue($result);
    }
}

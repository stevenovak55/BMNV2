<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Service;

use BMN\Platform\Database\DatabaseService;
use BMN\Schools\Model\ImportResult;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Service\SchoolDataService;
use BMN\Schools\Service\SchoolRankingService;
use PHPUnit\Framework\TestCase;

final class SchoolDataServiceTest extends TestCase
{
    private SchoolRepository $schoolRepo;
    private SchoolDistrictRepository $districtRepo;
    private SchoolDataRepository $dataRepo;
    private SchoolRankingService $rankingService;
    private \wpdb $wpdb;
    private DatabaseService $dbService;
    private SchoolDataService $service;

    protected function setUp(): void
    {
        $this->schoolRepo = $this->createMock(SchoolRepository::class);
        $this->districtRepo = $this->createMock(SchoolDistrictRepository::class);
        $this->dataRepo = $this->createMock(SchoolDataRepository::class);
        $this->rankingService = $this->createMock(SchoolRankingService::class);

        // DatabaseService is final, so use real instance with stub wpdb.
        $this->wpdb = new \wpdb();
        $this->dbService = new DatabaseService($this->wpdb);

        $this->service = new SchoolDataService(
            $this->schoolRepo,
            $this->districtRepo,
            $this->dataRepo,
            $this->rankingService,
            $this->dbService,
        );
    }

    // ------------------------------------------------------------------
    // ImportResult model
    // ------------------------------------------------------------------

    public function testImportResultTotal(): void
    {
        $result = new ImportResult(10, 5, 2, 1);
        $this->assertSame(17, $result->total());
    }

    public function testImportResultProperties(): void
    {
        $result = new ImportResult(3, 2, 1, 0, ['warning']);
        $this->assertSame(3, $result->created);
        $this->assertSame(2, $result->updated);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->errors);
        $this->assertSame(['warning'], $result->errorMessages);
    }

    // ------------------------------------------------------------------
    // importSchools
    // ------------------------------------------------------------------

    public function testImportSchoolsCreatesNew(): void
    {
        $this->schoolRepo->method('findByNcesId')->willReturn(null);
        $this->schoolRepo->method('create')->willReturn(1);

        $result = $this->service->importSchools([
            ['nces_school_id' => '250001', 'name' => 'Test School'],
        ]);

        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->updated);
    }

    public function testImportSchoolsUpdatesExisting(): void
    {
        $this->schoolRepo->method('findByNcesId')->willReturn(
            (object) ['id' => 1, 'nces_school_id' => '250001']
        );
        $this->schoolRepo->method('update')->willReturn(true);

        $result = $this->service->importSchools([
            ['nces_school_id' => '250001', 'name' => 'Updated School'],
        ]);

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->updated);
    }

    public function testImportSchoolsSkipsMissingFields(): void
    {
        $result = $this->service->importSchools([
            ['name' => 'No NCES ID'],
            ['nces_school_id' => '250001'], // Missing name.
        ]);

        $this->assertSame(0, $result->created);
        $this->assertSame(2, $result->errors);
        $this->assertCount(2, $result->errorMessages);
    }

    // ------------------------------------------------------------------
    // importDistricts
    // ------------------------------------------------------------------

    public function testImportDistrictsCreatesNew(): void
    {
        $this->districtRepo->method('findByNcesId')->willReturn(null);
        $this->districtRepo->method('create')->willReturn(1);

        $result = $this->service->importDistricts([
            ['nces_district_id' => '2501234', 'name' => 'Test District'],
        ]);

        $this->assertSame(1, $result->created);
    }

    public function testImportDistrictsUpdatesExisting(): void
    {
        $this->districtRepo->method('findByNcesId')->willReturn(
            (object) ['id' => 1]
        );
        $this->districtRepo->method('update')->willReturn(true);

        $result = $this->service->importDistricts([
            ['nces_district_id' => '2501234', 'name' => 'Updated District'],
        ]);

        $this->assertSame(1, $result->updated);
    }

    public function testImportDistrictsSkipsMissingFields(): void
    {
        $result = $this->service->importDistricts([
            ['name' => 'No ID'],
        ]);

        $this->assertSame(1, $result->errors);
    }

    // ------------------------------------------------------------------
    // importTestScores
    // ------------------------------------------------------------------

    public function testImportTestScoresBatchInserts(): void
    {
        // The stub wpdb will execute the batch insert via DatabaseService.
        // We just need the insert to succeed.
        $this->wpdb->query_result = 2;

        $result = $this->service->importTestScores([
            ['school_id' => 1, 'subject' => 'ELA', 'proficient_or_above_pct' => 85.0],
            ['school_id' => 1, 'subject' => 'Math', 'proficient_or_above_pct' => 78.0],
        ], 2025);

        $this->assertSame(2, $result->created);
    }

    public function testImportTestScoresSkipsMissingFields(): void
    {
        $result = $this->service->importTestScores([
            ['subject' => 'ELA'], // Missing school_id.
        ], 2025);

        $this->assertSame(1, $result->errors);
    }

    // ------------------------------------------------------------------
    // importFeatures
    // ------------------------------------------------------------------

    public function testImportFeaturesCreatesNew(): void
    {
        $this->wpdb->get_row_result = null; // No existing.
        $this->wpdb->insert_result = true;

        $result = $this->service->importFeatures([
            ['school_id' => 1, 'feature_type' => 'graduation_rate', 'feature_name' => 'rate', 'feature_value' => '95'],
        ]);

        $this->assertSame(1, $result->created);
    }

    public function testImportFeaturesUpdatesExisting(): void
    {
        $this->wpdb->get_row_result = (object) ['id' => 42];

        $result = $this->service->importFeatures([
            ['school_id' => 1, 'feature_type' => 'graduation_rate', 'feature_name' => 'rate', 'feature_value' => '96'],
        ]);

        $this->assertSame(1, $result->updated);
    }

    // ------------------------------------------------------------------
    // recalculateRankings
    // ------------------------------------------------------------------

    public function testRecalculateRankingsDelegatesToService(): void
    {
        $this->rankingService->expects($this->once())
            ->method('calculateAllRankings')
            ->with(2025)
            ->willReturn(['total' => 100, 'ranked' => 80, 'skipped' => 20]);

        $result = $this->service->recalculateRankings(2025);

        $this->assertSame(80, $result['ranked']);
    }

    // ------------------------------------------------------------------
    // getImportStats
    // ------------------------------------------------------------------

    public function testGetImportStatsReturnsAllCounts(): void
    {
        $this->schoolRepo->method('count')->willReturn(100);
        $this->districtRepo->method('count')->willReturn(50);
        $this->wpdb->get_var_result = '200';

        $stats = $this->service->getImportStats();

        $this->assertSame(100, $stats['schools']);
        $this->assertSame(50, $stats['districts']);
        $this->assertArrayHasKey('test_scores', $stats);
        $this->assertArrayHasKey('features', $stats);
        $this->assertArrayHasKey('demographics', $stats);
        $this->assertArrayHasKey('rankings', $stats);
    }
}

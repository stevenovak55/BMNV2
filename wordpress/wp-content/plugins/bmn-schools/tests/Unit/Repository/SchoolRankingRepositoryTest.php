<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Repository;

use BMN\Schools\Repository\SchoolRankingRepository;
use PHPUnit\Framework\TestCase;

final class SchoolRankingRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private SchoolRankingRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new SchoolRankingRepository($this->wpdb);
    }

    public function testStoreRankingInsertsNewRecord(): void
    {
        // No existing record.
        $this->wpdb->get_row_result = null;
        $this->wpdb->insert_result = true;

        $result = $this->repo->storeRanking(1, 2025, [
            'composite_score' => 85.0,
            'letter_grade' => 'A',
        ]);

        $this->assertTrue($result);
        // Should have SELECT (check existing) + INSERT.
        $this->assertGreaterThanOrEqual(2, count($this->wpdb->queries));
        $insertArgs = $this->wpdb->queries[1]['args'];
        $this->assertSame(1, $insertArgs['school_id']);
        $this->assertSame(2025, $insertArgs['year']);
        $this->assertSame(85.0, $insertArgs['composite_score']);
    }

    public function testStoreRankingUpdatesExistingRecord(): void
    {
        // Existing record found.
        $this->wpdb->get_row_result = (object) ['id' => 42];

        $result = $this->repo->storeRanking(1, 2025, [
            'composite_score' => 90.0,
        ]);

        $this->assertTrue($result);
        // Should have SELECT + UPDATE.
        $this->assertGreaterThanOrEqual(2, count($this->wpdb->queries));
        $updateSql = $this->wpdb->queries[1]['sql'];
        $this->assertStringContainsString('UPDATE', $updateSql);
    }

    public function testGetRankingWithYear(): void
    {
        $expected = (object) ['school_id' => 1, 'year' => 2025, 'composite_score' => 85.0];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->getRanking(1, 2025);

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('school_id', $sql);
        $this->assertStringContainsString('year', $sql);
    }

    public function testGetRankingWithoutYearGetsLatest(): void
    {
        $expected = (object) ['school_id' => 1, 'year' => 2025];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->getRanking(1);

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('ORDER BY year DESC', $sql);
    }

    public function testGetRankingReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->getRanking(999);

        $this->assertNull($result);
    }

    public function testGetTopSchoolsQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['school_id' => 1, 'composite_score' => 95.0, 'name' => 'Top School'],
        ];

        $result = $this->repo->getTopSchools(10, null, 2025);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('ORDER BY r.composite_score DESC', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testGetTopSchoolsWithCategoryFilter(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->getTopSchools(10, 'public_high', 2025);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('category', $sql);
    }

    public function testGetSchoolsByMinScoreFiltersCorrectly(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['school_id' => 1, 'composite_score' => 90.0],
        ];

        $result = $this->repo->getSchoolsByMinScore(80.0, 2025);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('composite_score >=', $sql);
    }

    public function testGetSchoolsByGradeFiltersCorrectly(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->getSchoolsByGrade('A', 2025);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('letter_grade', $sql);
    }

    public function testGetLatestDataYearReturnsMaxYear(): void
    {
        $this->wpdb->get_var_result = '2025';

        $result = $this->repo->getLatestDataYear();

        $this->assertSame(2025, $result);
    }

    public function testGetLatestDataYearReturnsCurrentYearWhenNoData(): void
    {
        $this->wpdb->get_var_result = null;

        $result = $this->repo->getLatestDataYear();

        $this->assertSame((int) date('Y'), $result);
    }

    public function testDeleteRankingsForYearReturnsCount(): void
    {
        $this->wpdb->query_result = 50;

        $result = $this->repo->deleteRankingsForYear(2024);

        $this->assertSame(50, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('DELETE', $sql);
        $this->assertStringContainsString('2024', $sql);
    }

    public function testStoreDistrictRankingInsertsNewRecord(): void
    {
        $this->wpdb->get_row_result = null;
        $this->wpdb->insert_result = true;

        $result = $this->repo->storeDistrictRanking(1, 2025, [
            'composite_score' => 82.0,
            'letter_grade' => 'A-',
        ]);

        $this->assertTrue($result);
        $insertArgs = $this->wpdb->queries[1]['args'];
        $this->assertSame(1, $insertArgs['district_id']);
    }

    public function testGetDistrictRankingReturnsObject(): void
    {
        $expected = (object) ['district_id' => 1, 'composite_score' => 82.0];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->getDistrictRanking(1, 2025);

        $this->assertSame($expected, $result);
    }
}

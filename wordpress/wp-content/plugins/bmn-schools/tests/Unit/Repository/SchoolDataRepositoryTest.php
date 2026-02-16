<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Repository;

use BMN\Schools\Repository\SchoolDataRepository;
use PHPUnit\Framework\TestCase;

final class SchoolDataRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private SchoolDataRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new SchoolDataRepository($this->wpdb);
    }

    public function testGetMcasScoresQueriesCorrectTable(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'subject' => 'ELA', 'proficient_or_above_pct' => 85.0],
            (object) ['id' => 2, 'subject' => 'Math', 'proficient_or_above_pct' => 78.0],
        ];

        $result = $this->repo->getMcasScores(1, 2025);

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('bmn_school_test_scores', $sql);
        $this->assertStringContainsString('school_id', $sql);
    }

    public function testGetMcasAverageReturnsFloat(): void
    {
        $this->wpdb->get_var_result = '81.5';

        $result = $this->repo->getMcasAverage(1, 2025);

        $this->assertSame(81.5, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('AVG(proficient_or_above_pct)', $sql);
    }

    public function testGetMcasAverageReturnsNullWhenNoData(): void
    {
        $this->wpdb->get_var_result = null;

        $result = $this->repo->getMcasAverage(1, 2025);

        $this->assertNull($result);
    }

    public function testGetMcasPreviousYearQueriesPriorYear(): void
    {
        $this->wpdb->get_var_result = '75.0';

        $result = $this->repo->getMcasPreviousYear(1, 2025);

        $this->assertSame(75.0, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        // Should query year 2024 (2025 - 1).
        $this->assertStringContainsString('2024', $sql);
    }

    public function testGetFeatureReturnsObject(): void
    {
        $expected = (object) ['id' => 1, 'feature_type' => 'graduation_rate', 'feature_value' => '96.5'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->getFeature(1, 'graduation_rate');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('feature_type', $sql);
    }

    public function testGetFeatureReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->getFeature(1, 'nonexistent');

        $this->assertNull($result);
    }

    public function testGetEnrollmentReturnsInt(): void
    {
        $this->wpdb->get_var_result = '1200';

        $result = $this->repo->getEnrollment(1, 2025);

        $this->assertSame(1200, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('total_students', $sql);
    }

    public function testGetEnrollmentReturnsNullWhenNoData(): void
    {
        $this->wpdb->get_var_result = null;

        $result = $this->repo->getEnrollment(1, 2025);

        $this->assertNull($result);
    }

    public function testGetDemographicsReturnsObject(): void
    {
        $expected = (object) ['school_id' => 1, 'year' => 2025, 'total_students' => 500];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->getDemographics(1, 2025);

        $this->assertSame($expected, $result);
    }

    public function testBatchGetMcasScoresGroupsBySchoolId(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['school_id' => 1, 'subject' => 'ELA'],
            (object) ['school_id' => 1, 'subject' => 'Math'],
            (object) ['school_id' => 2, 'subject' => 'ELA'],
        ];

        $result = $this->repo->batchGetMcasScores([1, 2], 2025);

        $this->assertCount(2, $result);
        $this->assertCount(2, $result[1]);
        $this->assertCount(1, $result[2]);
    }

    public function testBatchGetMcasScoresReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repo->batchGetMcasScores([], 2025);

        $this->assertSame([], $result);
        $this->assertCount(0, $this->wpdb->queries);
    }

    public function testBatchGetRankingsKeysBySchoolId(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['school_id' => 1, 'composite_score' => 85.0],
            (object) ['school_id' => 2, 'composite_score' => 72.0],
        ];

        $result = $this->repo->batchGetRankings([1, 2], 2025);

        $this->assertCount(2, $result);
        $this->assertSame(85.0, $result[1]->composite_score);
    }

    public function testBatchGetDemographicsKeysBySchoolId(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['school_id' => 1, 'total_students' => 500],
        ];

        $result = $this->repo->batchGetDemographics([1], 2025);

        $this->assertCount(1, $result);
        $this->assertSame(500, $result[1]->total_students);
    }

    public function testBatchGetFeaturesKeysBySchoolId(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['school_id' => 1, 'feature_type' => 'graduation_rate'],
        ];

        $result = $this->repo->batchGetFeatures([1], 'graduation_rate');

        $this->assertCount(1, $result);
        $this->assertSame('graduation_rate', $result[1]->feature_type);
    }
}

<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Service\SchoolRankingService;
use PHPUnit\Framework\TestCase;

final class SchoolRankingServiceTest extends TestCase
{
    private SchoolDataRepository $dataRepo;
    private SchoolRankingRepository $rankingRepo;
    private SchoolRepository $schoolRepo;
    private SchoolDistrictRepository $districtRepo;
    private CacheService $cache;
    private SchoolRankingService $service;

    protected function setUp(): void
    {
        $this->dataRepo = $this->createMock(SchoolDataRepository::class);
        $this->rankingRepo = $this->createMock(SchoolRankingRepository::class);
        $this->schoolRepo = $this->createMock(SchoolRepository::class);
        $this->districtRepo = $this->createMock(SchoolDistrictRepository::class);
        $this->cache = $this->createMock(CacheService::class);

        $this->service = new SchoolRankingService(
            $this->dataRepo,
            $this->rankingRepo,
            $this->schoolRepo,
            $this->districtRepo,
            $this->cache,
        );
    }

    // ------------------------------------------------------------------
    // Weight tests
    // ------------------------------------------------------------------

    public function testHighSchoolWeightsUsedForHighLevel(): void
    {
        $this->setupFullSchoolData('High');

        $result = $this->service->calculateSchoolScore(1, 'High');

        $this->assertTrue($result['has_data']);
        // High school should have graduation and ap components.
        $this->assertArrayHasKey('graduation', $result['components']);
        $this->assertArrayHasKey('ap', $result['components']);
    }

    public function testElementaryWeightsUsedForElementaryLevel(): void
    {
        $this->setupFullSchoolData('Elementary');

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertTrue($result['has_data']);
        // Elementary should NOT have graduation, masscore, or ap components.
        $this->assertArrayNotHasKey('graduation', $result['components']);
        $this->assertArrayNotHasKey('masscore', $result['components']);
        $this->assertArrayNotHasKey('ap', $result['components']);
    }

    public function testMiddleSchoolUsesHighSchoolWeights(): void
    {
        $this->setupFullSchoolData('Middle');

        $result = $this->service->calculateSchoolScore(1, 'Middle');

        $this->assertTrue($result['has_data']);
        $this->assertArrayHasKey('graduation', $result['components']);
    }

    // ------------------------------------------------------------------
    // Component scorer tests
    // ------------------------------------------------------------------

    public function testMcasProficiencyScoreFromAverage(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(82.5);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(82.5, $result['components']['mcas']);
    }

    public function testGrowthScoreCalculation(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        // +4 growth points: 50 + (4 * 2.5) = 60.
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(76.0);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(60.0, $result['components']['growth']);
    }

    public function testGrowthScoreNegativeGrowth(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(70.0);
        // -10 growth: 50 + (-10 * 2.5) = 25.
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(80.0);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(25.0, $result['components']['growth']);
    }

    public function testGrowthScoreClampedAt100(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(95.0);
        // +25 growth: 50 + (25 * 2.5) = 112.5, clamped to 100.
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(70.0);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(100.0, $result['components']['growth']);
    }

    public function testGrowthScoreClampedAt0(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(50.0);
        // -30 growth: 50 + (-30 * 2.5) = -25, clamped to 0.
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(80.0);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(0.0, $result['components']['growth']);
    }

    public function testPerPupilScoreLinearInterpolation(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        // $20K spending = midpoint: 25 + ((20000-10000)/20000)*75 = 62.5.
        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type): ?object {
                if ($type === 'per_pupil_spending') {
                    return (object) ['feature_value' => '20000'];
                }
                return null;
            }
        );

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(62.5, $result['components']['spending']);
    }

    public function testPerPupilScoreMinimum(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        // $5K spending = clamped to 25.
        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type): ?object {
                if ($type === 'per_pupil_spending') {
                    return (object) ['feature_value' => '5000'];
                }
                return null;
            }
        );

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(25.0, $result['components']['spending']);
    }

    public function testRatioScoreLinearInterpolation(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        // 20:1 ratio = midpoint: 100 - ((20-10)/20)*100 = 50.
        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type): ?object {
                if ($type === 'student_teacher_ratio') {
                    return (object) ['feature_value' => '20'];
                }
                return null;
            }
        );

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(50.0, $result['components']['ratio']);
    }

    public function testAttendanceScoreFromChronicAbsence(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        // 15% chronic absence = 100-15 = 85.
        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type): ?object {
                if ($type === 'chronic_absence_rate') {
                    return (object) ['feature_value' => '15'];
                }
                return null;
            }
        );

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame(85.0, $result['components']['attendance']);
    }

    // ------------------------------------------------------------------
    // Composite calculation
    // ------------------------------------------------------------------

    public function testCompositeScoreIsWeightedAverage(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        // MCAS = 80, no other data => only MCAS is available.
        // But we need 3+ components, so let's provide 3.
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(76.0); // growth = 50 + 4*2.5 = 60.
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type): ?object {
                if ($type === 'chronic_absence_rate') {
                    return (object) ['feature_value' => '10']; // attendance = 90.
                }
                return null;
            }
        );

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertTrue($result['has_data']);
        $this->assertGreaterThan(0, $result['composite_score']);
        $this->assertSame(3, $result['data_count']);
    }

    public function testMcasRequiredForRanking(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(null); // No MCAS.
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $result = $this->service->calculateSchoolScore(1, 'High');

        $this->assertFalse($result['has_data']);
        $this->assertSame(0.0, $result['composite_score']);
        $this->assertSame('insufficient', $result['confidence_level']);
    }

    public function testMinimumThreeComponentsRequired(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        // Only MCAS available (1 component).
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertFalse($result['has_data']);
        $this->assertSame(1, $result['data_count']);
    }

    // ------------------------------------------------------------------
    // Confidence penalties
    // ------------------------------------------------------------------

    public function testComprehensiveConfidenceNoPenalty(): void
    {
        // 7+ components for high school = comprehensive = 0% penalty.
        $this->setupFullSchoolData('High');

        $result = $this->service->calculateSchoolScore(1, 'High');

        $this->assertSame('comprehensive', $result['confidence_level']);
    }

    public function testElementaryComprehensiveAt5Components(): void
    {
        // Elementary with all 5 components = comprehensive.
        $this->setupFullSchoolData('Elementary');

        $result = $this->service->calculateSchoolScore(1, 'Elementary');

        $this->assertSame('comprehensive', $result['confidence_level']);
    }

    public function testGoodConfidenceFiveToSixComponentsHighSchool(): void
    {
        // High school with 5 components = good (-5%).
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(76.0);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $callCount = 0;
        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type) use (&$callCount): ?object {
                $callCount++;
                if ($type === 'chronic_absence_rate') {
                    return (object) ['feature_value' => '10'];
                }
                if ($type === 'student_teacher_ratio') {
                    return (object) ['feature_value' => '15'];
                }
                if ($type === 'per_pupil_spending') {
                    return (object) ['feature_value' => '20000'];
                }
                return null;
            }
        );

        $result = $this->service->calculateSchoolScore(1, 'High');

        $this->assertSame('good', $result['confidence_level']);
    }

    public function testLimitedConfidenceThreeToFourComponents(): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(76.0);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type): ?object {
                if ($type === 'chronic_absence_rate') {
                    return (object) ['feature_value' => '10'];
                }
                return null;
            }
        );

        $result = $this->service->calculateSchoolScore(1, 'High');

        $this->assertSame('limited', $result['confidence_level']);
        $this->assertSame(3, $result['data_count']);
    }

    // ------------------------------------------------------------------
    // Percentile ranks
    // ------------------------------------------------------------------

    public function testPercentileRanksAssignedWithinCategory(): void
    {
        $scores = [
            ['category' => 'public_high', 'composite_score' => 90.0],
            ['category' => 'public_high', 'composite_score' => 70.0],
            ['category' => 'public_high', 'composite_score' => 80.0],
        ];

        $this->service->calculatePercentileRanks($scores);

        // Ranked: 90 (rank 1, percentile 100), 80 (rank 2, percentile 50), 70 (rank 3, percentile 0).
        $this->assertSame(100.0, $scores[0]['percentile_rank']);
        $this->assertSame(1, $scores[0]['state_rank']);
        $this->assertSame(50.0, $scores[2]['percentile_rank']);
        $this->assertSame(2, $scores[2]['state_rank']);
        $this->assertSame(0.0, $scores[1]['percentile_rank']);
        $this->assertSame(3, $scores[1]['state_rank']);
    }

    public function testPercentileRanksSeparateByCategory(): void
    {
        $scores = [
            ['category' => 'public_high', 'composite_score' => 90.0],
            ['category' => 'public_elementary', 'composite_score' => 85.0],
            ['category' => 'public_high', 'composite_score' => 80.0],
        ];

        $this->service->calculatePercentileRanks($scores);

        // public_high: 90 (rank 1), 80 (rank 2).
        $this->assertSame(1, $scores[0]['state_rank']);
        $this->assertSame(2, $scores[2]['state_rank']);
        // public_elementary: 85 (rank 1, only one — percentile 100).
        $this->assertSame(1, $scores[1]['state_rank']);
        $this->assertSame(100.0, $scores[1]['percentile_rank']);
    }

    // ------------------------------------------------------------------
    // Letter grades
    // ------------------------------------------------------------------

    public function testLetterGradeAPlusAt90(): void
    {
        $this->assertSame('A+', $this->service->getLetterGrade(95.0));
        $this->assertSame('A+', $this->service->getLetterGrade(90.0));
    }

    public function testLetterGradeAAt80(): void
    {
        $this->assertSame('A', $this->service->getLetterGrade(85.0));
        $this->assertSame('A', $this->service->getLetterGrade(80.0));
    }

    public function testLetterGradeBAt50(): void
    {
        $this->assertSame('B', $this->service->getLetterGrade(55.0));
        $this->assertSame('B', $this->service->getLetterGrade(50.0));
    }

    public function testLetterGradeFAt0(): void
    {
        $this->assertSame('F', $this->service->getLetterGrade(0.0));
    }

    public function testLetterGradeAllThresholds(): void
    {
        $this->assertSame('A-', $this->service->getLetterGrade(75.0));
        $this->assertSame('B+', $this->service->getLetterGrade(65.0));
        $this->assertSame('B-', $this->service->getLetterGrade(45.0));
        $this->assertSame('C+', $this->service->getLetterGrade(35.0));
        $this->assertSame('C', $this->service->getLetterGrade(25.0));
        $this->assertSame('C-', $this->service->getLetterGrade(15.0));
        $this->assertSame('D', $this->service->getLetterGrade(5.0));
    }

    // ------------------------------------------------------------------
    // Reliability factor
    // ------------------------------------------------------------------

    public function testReliabilityFactor300Plus(): void
    {
        $this->assertSame(1.0, $this->service->getEnrollmentReliabilityFactor(500));
        $this->assertSame(1.0, $this->service->getEnrollmentReliabilityFactor(300));
    }

    public function testReliabilityFactor100To300(): void
    {
        // 200 students: 0.90 + ((200-100)/200)*0.10 = 0.95.
        $this->assertEqualsWithDelta(0.95, $this->service->getEnrollmentReliabilityFactor(200), 0.001);
    }

    public function testReliabilityFactorBelow100(): void
    {
        // 50 students: 0.75 + (50/100)*0.15 = 0.825.
        $this->assertSame(0.825, $this->service->getEnrollmentReliabilityFactor(50));
    }

    public function testReliabilityFactorNull(): void
    {
        $this->assertSame(0.85, $this->service->getEnrollmentReliabilityFactor(null));
    }

    // ------------------------------------------------------------------
    // Highlights
    // ------------------------------------------------------------------

    public function testHighlightsReturnMaxFour(): void
    {
        $this->cache->method('remember')->willReturnCallback(
            fn ($key, $ttl, $callback) => $callback()
        );
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->rankingRepo->method('getRanking')->willReturn((object) [
            'ap_score' => 80,
            'graduation_score' => 95,
            'ratio_score' => 80,
            'spending_score' => 75,
            'attendance_score' => 90,
            'mcas_score' => 90,
            'growth_score' => 70,
            'masscore_score' => 85,
        ]);

        $highlights = $this->service->getSchoolHighlights(1, 2025);

        $this->assertCount(4, $highlights);
    }

    public function testHighlightsEmptyWhenNoRanking(): void
    {
        $this->cache->method('remember')->willReturnCallback(
            fn ($key, $ttl, $callback) => $callback()
        );
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->rankingRepo->method('getRanking')->willReturn(null);

        $highlights = $this->service->getSchoolHighlights(1, 2025);

        $this->assertSame([], $highlights);
    }

    public function testHighlightsIncludesStrongAp(): void
    {
        $this->cache->method('remember')->willReturnCallback(
            fn ($key, $ttl, $callback) => $callback()
        );
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->rankingRepo->method('getRanking')->willReturn((object) [
            'ap_score' => 75,
            'graduation_score' => null,
            'ratio_score' => null,
            'spending_score' => null,
            'attendance_score' => null,
            'mcas_score' => null,
            'growth_score' => null,
            'masscore_score' => null,
        ]);

        $highlights = $this->service->getSchoolHighlights(1, 2025);

        $this->assertContains('Strong AP Programs', $highlights);
    }

    // ------------------------------------------------------------------
    // determineLevel
    // ------------------------------------------------------------------

    public function testDetermineLevelElementary(): void
    {
        $this->assertSame('Elementary', $this->service->determineLevel('K', '5'));
        $this->assertSame('Elementary', $this->service->determineLevel('PK', '4'));
    }

    public function testDetermineLevelMiddle(): void
    {
        $this->assertSame('Middle', $this->service->determineLevel('6', '8'));
    }

    public function testDetermineLevelHigh(): void
    {
        $this->assertSame('High', $this->service->determineLevel('9', '12'));
    }

    // ------------------------------------------------------------------
    // Edge cases
    // ------------------------------------------------------------------

    public function testCalculateSchoolScoreLooksUpLevel(): void
    {
        $this->schoolRepo->method('find')->willReturn(
            (object) ['id' => 1, 'level' => 'High']
        );
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(null);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(null);
        $this->dataRepo->method('getFeature')->willReturn(null);
        $this->dataRepo->method('getEnrollment')->willReturn(null);

        // Should look up level from school when not passed.
        $result = $this->service->calculateSchoolScore(1);

        $this->assertFalse($result['has_data']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function setupFullSchoolData(string $level): void
    {
        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);
        $this->dataRepo->method('getMcasAverage')->willReturn(80.0);
        $this->dataRepo->method('getMcasPreviousYear')->willReturn(76.0);
        $this->dataRepo->method('getEnrollment')->willReturn(500);

        $this->dataRepo->method('getFeature')->willReturnCallback(
            function (int $id, string $type): ?object {
                return match ($type) {
                    'chronic_absence_rate' => (object) ['feature_value' => '10'],
                    'student_teacher_ratio' => (object) ['feature_value' => '15'],
                    'per_pupil_spending' => (object) ['feature_value' => '20000'],
                    'graduation_rate' => (object) ['feature_value' => '95'],
                    'masscore_completion' => (object) ['feature_value' => '85'],
                    'ap_performance' => (object) ['feature_value' => json_encode([
                        'pass_rate' => 70,
                        'participation_rate' => 60,
                    ])],
                    default => null,
                };
            }
        );
    }
}

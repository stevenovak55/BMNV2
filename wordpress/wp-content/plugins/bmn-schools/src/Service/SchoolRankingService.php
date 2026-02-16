<?php

declare(strict_types=1);

namespace BMN\Schools\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;

/**
 * School ranking service — full port of v1 class-ranking-calculator.php.
 *
 * Calculates composite school scores using 8 component metrics,
 * with level-specific weights for Elementary vs High/Middle schools.
 */
class SchoolRankingService
{
    /**
     * Weight sets by school level.
     */
    private const HIGH_MIDDLE_WEIGHTS = [
        'mcas'       => 0.30,
        'graduation' => 0.15,
        'growth'     => 0.12,
        'ratio'      => 0.10,
        'masscore'   => 0.10,
        'attendance' => 0.10,
        'ap'         => 0.08,
        'spending'   => 0.05,
    ];

    private const ELEMENTARY_WEIGHTS = [
        'mcas'       => 0.40,
        'attendance' => 0.20,
        'ratio'      => 0.15,
        'growth'     => 0.15,
        'spending'   => 0.10,
    ];

    /**
     * Grade thresholds (percentile-based).
     */
    private const GRADE_THRESHOLDS = [
        ['min' => 90, 'grade' => 'A+'],
        ['min' => 80, 'grade' => 'A'],
        ['min' => 70, 'grade' => 'A-'],
        ['min' => 60, 'grade' => 'B+'],
        ['min' => 50, 'grade' => 'B'],
        ['min' => 40, 'grade' => 'B-'],
        ['min' => 30, 'grade' => 'C+'],
        ['min' => 20, 'grade' => 'C'],
        ['min' => 10, 'grade' => 'C-'],
        ['min' => 1,  'grade' => 'D'],
        ['min' => 0,  'grade' => 'F'],
    ];

    private readonly SchoolDataRepository $dataRepo;
    private readonly SchoolRankingRepository $rankingRepo;
    private readonly SchoolRepository $schoolRepo;
    private readonly SchoolDistrictRepository $districtRepo;
    private readonly CacheService $cache;

    public function __construct(
        SchoolDataRepository $dataRepo,
        SchoolRankingRepository $rankingRepo,
        SchoolRepository $schoolRepo,
        SchoolDistrictRepository $districtRepo,
        CacheService $cache,
    ) {
        $this->dataRepo = $dataRepo;
        $this->rankingRepo = $rankingRepo;
        $this->schoolRepo = $schoolRepo;
        $this->districtRepo = $districtRepo;
        $this->cache = $cache;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Calculate the composite score for a single school.
     *
     * @return array{composite_score: float, has_data: bool, components: array, data_count: int, confidence_level: string}
     */
    public function calculateSchoolScore(int $schoolId, ?string $level = null): array
    {
        if ($level === null) {
            $school = $this->schoolRepo->find($schoolId);
            $level = $school ? $school->level : 'Other';
        }

        $year = $this->rankingRepo->getLatestDataYear();
        $isElementary = ($level === 'Elementary');
        $weights = $isElementary ? self::ELEMENTARY_WEIGHTS : self::HIGH_MIDDLE_WEIGHTS;

        // Calculate each component score.
        $components = [];
        $components['mcas'] = $this->getMcasProficiencyScore($schoolId, $year);
        $components['attendance'] = $this->getAttendanceScore($schoolId);
        $components['growth'] = $this->getMcasGrowthScore($schoolId, $year);
        $components['ratio'] = $this->getRatioScore($schoolId);
        $components['spending'] = $this->getPerPupilScore($schoolId);

        if (! $isElementary) {
            $components['graduation'] = $this->getGraduationScore($schoolId);
            $components['masscore'] = $this->getMasscoreScore($schoolId);
            $components['ap'] = $this->getApScore($schoolId);
        }

        // MCAS is required for ranking.
        if ($components['mcas'] === null) {
            return [
                'composite_score' => 0.0,
                'has_data' => false,
                'components' => $components,
                'data_count' => 0,
                'confidence_level' => 'insufficient',
            ];
        }

        // Count available data components.
        $availableComponents = array_filter($components, static fn ($v) => $v !== null);
        $dataCount = count($availableComponents);

        // Minimum 3 components required.
        if ($dataCount < 3) {
            return [
                'composite_score' => 0.0,
                'has_data' => false,
                'components' => $components,
                'data_count' => $dataCount,
                'confidence_level' => 'insufficient',
            ];
        }

        // Calculate weighted composite score, redistributing missing weights.
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($weights as $key => $weight) {
            if (isset($availableComponents[$key])) {
                $totalWeight += $weight;
                $weightedSum += $availableComponents[$key] * $weight;
            }
        }

        $compositeScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;

        // Apply confidence penalty.
        $confidenceLevel = $this->getConfidenceLevel($dataCount, $isElementary);
        $penalty = $this->getConfidencePenalty($dataCount, $isElementary);
        $compositeScore *= (1.0 - $penalty);

        // Apply enrollment reliability factor.
        $enrollment = $this->dataRepo->getEnrollment($schoolId, $year);
        $reliabilityFactor = $this->getEnrollmentReliabilityFactor($enrollment);
        $compositeScore *= $reliabilityFactor;

        return [
            'composite_score' => round($compositeScore, 2),
            'has_data' => true,
            'components' => $components,
            'data_count' => $dataCount,
            'confidence_level' => $confidenceLevel,
        ];
    }

    /**
     * Calculate and store rankings for all schools.
     *
     * @return array{total: int, ranked: int, skipped: int}
     */
    public function calculateAllRankings(?int $year = null): array
    {
        $year = $year ?? $this->rankingRepo->getLatestDataYear();

        // Get all schools.
        $schools = $this->schoolRepo->findBy([], 0);
        $scores = [];
        $ranked = 0;
        $skipped = 0;

        foreach ($schools as $school) {
            $result = $this->calculateSchoolScore((int) $school->id, $school->level);

            if (! $result['has_data']) {
                $skipped++;
                continue;
            }

            $category = strtolower($school->school_type) . '_' . strtolower($school->level);

            $scores[] = [
                'school_id' => (int) $school->id,
                'composite_score' => $result['composite_score'],
                'category' => $category,
                'level' => $school->level,
                'components' => $result['components'],
                'data_count' => $result['data_count'],
                'confidence_level' => $result['confidence_level'],
            ];

            $ranked++;
        }

        // Calculate percentile ranks within categories.
        $this->calculatePercentileRanks($scores);

        // Store all rankings.
        foreach ($scores as $score) {
            $this->rankingRepo->storeRanking($score['school_id'], $year, [
                'category' => $score['category'],
                'composite_score' => $score['composite_score'],
                'percentile_rank' => $score['percentile_rank'] ?? null,
                'state_rank' => $score['state_rank'] ?? null,
                'letter_grade' => $score['letter_grade'] ?? null,
                'mcas_score' => $score['components']['mcas'] ?? null,
                'graduation_score' => $score['components']['graduation'] ?? null,
                'masscore_score' => $score['components']['masscore'] ?? null,
                'attendance_score' => $score['components']['attendance'] ?? null,
                'ap_score' => $score['components']['ap'] ?? null,
                'growth_score' => $score['components']['growth'] ?? null,
                'spending_score' => $score['components']['spending'] ?? null,
                'ratio_score' => $score['components']['ratio'] ?? null,
                'data_components' => $score['data_count'],
                'confidence_level' => $score['confidence_level'],
            ]);
        }

        // Invalidate cache.
        $this->cache->invalidateGroup('schools');

        return [
            'total' => count($schools),
            'ranked' => $ranked,
            'skipped' => $skipped,
        ];
    }

    /**
     * Calculate percentile ranks within categories and assign letter grades.
     *
     * @param array[] &$scores Scores array, modified in place.
     */
    public function calculatePercentileRanks(array &$scores): void
    {
        // Group by category.
        $categories = [];
        foreach ($scores as $i => $score) {
            $categories[$score['category']][] = $i;
        }

        foreach ($categories as $indices) {
            // Sort indices by composite_score descending.
            usort($indices, static fn (int $a, int $b): int => $scores[$b]['composite_score'] <=> $scores[$a]['composite_score']);

            $count = count($indices);
            foreach ($indices as $rank => $index) {
                $percentile = $count > 1
                    ? round(100 * ($count - 1 - $rank) / ($count - 1), 2)
                    : 100.0;

                $scores[$index]['percentile_rank'] = $percentile;
                $scores[$index]['state_rank'] = $rank + 1;
                $scores[$index]['letter_grade'] = $this->getLetterGrade($percentile);
            }
        }
    }

    /**
     * Calculate district rankings using enrollment-weighted averages.
     *
     * @return array{total: int, ranked: int}
     */
    public function calculateDistrictRankings(?int $year = null): array
    {
        $year = $year ?? $this->rankingRepo->getLatestDataYear();
        $districts = $this->districtRepo->findBy([], 0);
        $ranked = 0;

        foreach ($districts as $district) {
            $districtId = (int) $district->id;
            $schools = $this->schoolRepo->findByDistrict($districtId);

            if (count($schools) < 2) {
                continue;
            }

            $totalWeight = 0.0;
            $weightedSum = 0.0;
            $schoolsWithData = 0;
            $levelTotals = ['Elementary' => [], 'Middle' => [], 'High' => []];

            foreach ($schools as $school) {
                // Skip private schools from district rankings.
                if ($school->school_type === 'private') {
                    continue;
                }

                $ranking = $this->rankingRepo->getRanking((int) $school->id, $year);
                if ($ranking === null || $ranking->composite_score === null) {
                    continue;
                }

                $enrollment = $this->dataRepo->getEnrollment((int) $school->id, $year) ?? 100;
                $score = (float) $ranking->composite_score;

                $totalWeight += $enrollment;
                $weightedSum += $score * $enrollment;
                $schoolsWithData++;

                if (isset($levelTotals[$school->level])) {
                    $levelTotals[$school->level][] = $score;
                }
            }

            if ($schoolsWithData < 2) {
                continue;
            }

            $compositeScore = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0.0;

            $levelAverages = [];
            foreach ($levelTotals as $level => $levelScores) {
                $levelAverages[$level] = $levelScores !== []
                    ? round(array_sum($levelScores) / count($levelScores), 2)
                    : null;
            }

            $this->rankingRepo->storeDistrictRanking($districtId, $year, [
                'composite_score' => $compositeScore,
                'schools_count' => count($schools),
                'schools_with_data' => $schoolsWithData,
                'elementary_avg' => $levelAverages['Elementary'],
                'middle_avg' => $levelAverages['Middle'],
                'high_avg' => $levelAverages['High'],
            ]);

            $ranked++;
        }

        return [
            'total' => count($districts),
            'ranked' => $ranked,
        ];
    }

    /**
     * Get letter grade from percentile rank.
     */
    public function getLetterGrade(float $percentile): string
    {
        foreach (self::GRADE_THRESHOLDS as $threshold) {
            if ($percentile >= $threshold['min']) {
                return $threshold['grade'];
            }
        }

        return 'F';
    }

    /**
     * Get enrollment-based reliability factor (0.75 to 1.0).
     *
     * Small schools get a penalty because their data is less statistically reliable.
     */
    public function getEnrollmentReliabilityFactor(?int $enrollment): float
    {
        if ($enrollment === null || $enrollment <= 0) {
            return 0.85; // Default for unknown enrollment.
        }

        if ($enrollment >= 300) {
            return 1.0;
        }

        if ($enrollment >= 100) {
            // Linear scale from 0.90 to 1.0 for 100-300 students.
            return 0.90 + (($enrollment - 100) / 200) * 0.10;
        }

        // Below 100 students: 0.75 to 0.90.
        return 0.75 + ($enrollment / 100) * 0.15;
    }

    /**
     * Get school highlights — top 4 notable attributes.
     *
     * @return string[]
     */
    public function getSchoolHighlights(int $schoolId, ?int $year = null): array
    {
        $cacheKey = "highlights_{$schoolId}_{$year}";

        return $this->cache->remember($cacheKey, 3600, function () use ($schoolId, $year): array {
            $year = $year ?? $this->rankingRepo->getLatestDataYear();
            $ranking = $this->rankingRepo->getRanking($schoolId, $year);

            if ($ranking === null) {
                return [];
            }

            $highlights = [];

            // AP Programs.
            if (isset($ranking->ap_score) && (float) $ranking->ap_score >= 70) {
                $highlights[] = 'Strong AP Programs';
            }

            // Graduation Rate.
            if (isset($ranking->graduation_score) && (float) $ranking->graduation_score >= 90) {
                $highlights[] = 'High Graduation Rate';
            }

            // Class Size.
            if (isset($ranking->ratio_score) && (float) $ranking->ratio_score >= 75) {
                $highlights[] = 'Low Class Size';
            }

            // Resources.
            if (isset($ranking->spending_score) && (float) $ranking->spending_score >= 70) {
                $highlights[] = 'Well Resourced';
            }

            // Attendance.
            if (isset($ranking->attendance_score) && (float) $ranking->attendance_score >= 85) {
                $highlights[] = 'Strong Attendance';
            }

            // MCAS Excellence.
            if (isset($ranking->mcas_score) && (float) $ranking->mcas_score >= 85) {
                $highlights[] = 'High Test Scores';
            }

            // Growth.
            if (isset($ranking->growth_score) && (float) $ranking->growth_score >= 65) {
                $highlights[] = 'Strong Growth';
            }

            // MassCore.
            if (isset($ranking->masscore_score) && (float) $ranking->masscore_score >= 80) {
                $highlights[] = 'MassCore Leader';
            }

            return array_slice($highlights, 0, 4);
        }, 'schools');
    }

    /**
     * Determine school level from grade range.
     */
    public function determineLevel(string $gradesLow, string $gradesHigh): string
    {
        $low = $this->gradeToNumber($gradesLow);
        $high = $this->gradeToNumber($gradesHigh);

        if ($high <= 5) {
            return 'Elementary';
        }

        if ($low >= 9) {
            return 'High';
        }

        if ($low >= 6 && $high <= 8) {
            return 'Middle';
        }

        // Mixed: determine by the majority of grades.
        $midpoint = ($low + $high) / 2;

        if ($midpoint <= 5) {
            return 'Elementary';
        }

        if ($midpoint <= 8) {
            return 'Middle';
        }

        return 'High';
    }

    // ------------------------------------------------------------------
    // Component scoring methods (private, return ?float 0-100)
    // ------------------------------------------------------------------

    /**
     * MCAS proficiency score: average proficient_or_above_pct.
     */
    private function getMcasProficiencyScore(int $schoolId, int $year): ?float
    {
        return $this->dataRepo->getMcasAverage($schoolId, $year);
    }

    /**
     * Graduation rate score (from features table).
     */
    private function getGraduationScore(int $schoolId): ?float
    {
        $feature = $this->dataRepo->getFeature($schoolId, 'graduation_rate');
        if ($feature === null || $feature->feature_value === null) {
            return null;
        }

        $value = json_decode($feature->feature_value, true);
        $rate = is_array($value) ? ($value['rate'] ?? null) : (float) $feature->feature_value;

        return $rate !== null ? min(100.0, max(0.0, (float) $rate)) : null;
    }

    /**
     * MassCore completion percentage score.
     */
    private function getMasscoreScore(int $schoolId): ?float
    {
        $feature = $this->dataRepo->getFeature($schoolId, 'masscore_completion');
        if ($feature === null || $feature->feature_value === null) {
            return null;
        }

        $value = json_decode($feature->feature_value, true);
        $rate = is_array($value) ? ($value['rate'] ?? null) : (float) $feature->feature_value;

        return $rate !== null ? min(100.0, max(0.0, (float) $rate)) : null;
    }

    /**
     * Attendance score: 100 - chronic_absence_rate.
     */
    private function getAttendanceScore(int $schoolId): ?float
    {
        $feature = $this->dataRepo->getFeature($schoolId, 'chronic_absence_rate');
        if ($feature === null || $feature->feature_value === null) {
            return null;
        }

        $value = json_decode($feature->feature_value, true);
        $rate = is_array($value) ? ($value['rate'] ?? null) : (float) $feature->feature_value;

        if ($rate === null) {
            return null;
        }

        return min(100.0, max(0.0, 100.0 - (float) $rate));
    }

    /**
     * AP score: combined pass rate and participation.
     */
    private function getApScore(int $schoolId): ?float
    {
        $feature = $this->dataRepo->getFeature($schoolId, 'ap_performance');
        if ($feature === null || $feature->feature_value === null) {
            return null;
        }

        $value = json_decode($feature->feature_value, true);
        if (! is_array($value)) {
            return null;
        }

        $passRate = $value['pass_rate'] ?? null;
        $participation = $value['participation_rate'] ?? null;

        if ($passRate === null && $participation === null) {
            return null;
        }

        // Weighted blend: 70% pass rate, 30% participation.
        $score = 0.0;
        $weight = 0.0;

        if ($passRate !== null) {
            $score += (float) $passRate * 0.7;
            $weight += 0.7;
        }

        if ($participation !== null) {
            $score += (float) $participation * 0.3;
            $weight += 0.3;
        }

        return $weight > 0 ? min(100.0, max(0.0, $score / $weight)) : null;
    }

    /**
     * MCAS growth score: year-over-year delta, scaled.
     *
     * Formula: 50 + (growth * 2.5), clamped 0-100
     */
    private function getMcasGrowthScore(int $schoolId, int $year): ?float
    {
        $currentAvg = $this->dataRepo->getMcasAverage($schoolId, $year);
        $previousAvg = $this->dataRepo->getMcasPreviousYear($schoolId, $year);

        if ($currentAvg === null || $previousAvg === null) {
            return null;
        }

        $growth = $currentAvg - $previousAvg;
        $score = 50.0 + ($growth * 2.5);

        return min(100.0, max(0.0, $score));
    }

    /**
     * Per-pupil spending score: linear $10K=25 to $30K=100.
     */
    private function getPerPupilScore(int $schoolId): ?float
    {
        $feature = $this->dataRepo->getFeature($schoolId, 'per_pupil_spending');
        if ($feature === null || $feature->feature_value === null) {
            return null;
        }

        $value = json_decode($feature->feature_value, true);
        $spending = is_array($value) ? ($value['amount'] ?? null) : (float) $feature->feature_value;

        if ($spending === null) {
            return null;
        }

        $spending = (float) $spending;

        if ($spending <= 10000) {
            return 25.0;
        }

        if ($spending >= 30000) {
            return 100.0;
        }

        // Linear interpolation: $10K=25, $30K=100.
        return 25.0 + (($spending - 10000) / 20000) * 75.0;
    }

    /**
     * Student-teacher ratio score: linear 10:1=100 to 30:1=0.
     */
    private function getRatioScore(int $schoolId): ?float
    {
        $feature = $this->dataRepo->getFeature($schoolId, 'student_teacher_ratio');
        if ($feature === null || $feature->feature_value === null) {
            return null;
        }

        $value = json_decode($feature->feature_value, true);
        $ratio = is_array($value) ? ($value['ratio'] ?? null) : (float) $feature->feature_value;

        if ($ratio === null) {
            return null;
        }

        $ratio = (float) $ratio;

        if ($ratio <= 10) {
            return 100.0;
        }

        if ($ratio >= 30) {
            return 0.0;
        }

        // Linear: 10:1=100, 30:1=0.
        return 100.0 - (($ratio - 10) / 20) * 100.0;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Get confidence level string from data count.
     */
    private function getConfidenceLevel(int $dataCount, bool $isElementary): string
    {
        $comprehensive = $isElementary ? 5 : 7;
        $good = $isElementary ? 4 : 5;

        if ($dataCount >= $comprehensive) {
            return 'comprehensive';
        }

        if ($dataCount >= $good) {
            return 'good';
        }

        if ($dataCount >= 3) {
            return 'limited';
        }

        return 'insufficient';
    }

    /**
     * Get confidence penalty from data count.
     */
    private function getConfidencePenalty(int $dataCount, bool $isElementary): float
    {
        $comprehensive = $isElementary ? 5 : 7;
        $good = $isElementary ? 4 : 5;

        if ($dataCount >= $comprehensive) {
            return 0.0;
        }

        if ($dataCount >= $good) {
            return 0.05;
        }

        if ($dataCount >= 3) {
            return 0.10;
        }

        return 1.0; // Full penalty = not ranked.
    }

    /**
     * Convert grade string to numeric value.
     */
    private function gradeToNumber(string $grade): int
    {
        $grade = strtoupper(trim($grade));

        return match ($grade) {
            'PK', 'PRE-K' => -1,
            'K', 'KG' => 0,
            default => (int) $grade,
        };
    }
}

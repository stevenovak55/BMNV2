<?php

declare(strict_types=1);

namespace BMN\Schools\Repository;

use wpdb;

/**
 * Repository for school data tables: test_scores, features, demographics.
 *
 * Standalone (no base class) because it spans multiple tables.
 */
class SchoolDataRepository
{
    private readonly wpdb $wpdb;
    private readonly string $testScoresTable;
    private readonly string $featuresTable;
    private readonly string $demographicsTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->testScoresTable = $wpdb->prefix . 'bmn_school_test_scores';
        $this->featuresTable = $wpdb->prefix . 'bmn_school_features';
        $this->demographicsTable = $wpdb->prefix . 'bmn_school_demographics';
    }

    // ------------------------------------------------------------------
    // MCAS / Test Scores
    // ------------------------------------------------------------------

    /**
     * Get MCAS test scores for a school in a given year.
     *
     * @return object[]
     */
    public function getMcasScores(int $schoolId, int $year): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->testScoresTable} WHERE school_id = %d AND year = %d ORDER BY subject ASC",
                $schoolId,
                $year
            )
        );
    }

    /**
     * Get the average proficient_or_above_pct across all subjects for a school.
     */
    public function getMcasAverage(int $schoolId, int $year): ?float
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT AVG(proficient_or_above_pct) FROM {$this->testScoresTable}
                 WHERE school_id = %d AND year = %d AND proficient_or_above_pct IS NOT NULL",
                $schoolId,
                $year
            )
        );

        return $result !== null ? (float) $result : null;
    }

    /**
     * Get the previous year's MCAS average (for growth calculation).
     */
    public function getMcasPreviousYear(int $schoolId, int $year): ?float
    {
        return $this->getMcasAverage($schoolId, $year - 1);
    }

    // ------------------------------------------------------------------
    // Features
    // ------------------------------------------------------------------

    /**
     * Get a specific feature for a school.
     */
    public function getFeature(int $schoolId, string $featureType): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->featuresTable} WHERE school_id = %d AND feature_type = %s LIMIT 1",
                $schoolId,
                $featureType
            )
        );

        return $result ?: null;
    }

    // ------------------------------------------------------------------
    // Demographics / Enrollment
    // ------------------------------------------------------------------

    /**
     * Get enrollment (total_students) for a school in a given year.
     */
    public function getEnrollment(int $schoolId, int $year): ?int
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT total_students FROM {$this->demographicsTable} WHERE school_id = %d AND year = %d",
                $schoolId,
                $year
            )
        );

        return $result !== null ? (int) $result : null;
    }

    /**
     * Get total enrollment for a district (sum of all schools' students).
     */
    public function getDistrictEnrollment(int $districtId): ?int
    {
        // Join demographics with schools to get district total.
        $schoolsTable = $this->wpdb->prefix . 'bmn_schools';

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(d.total_students)
                 FROM {$this->demographicsTable} d
                 INNER JOIN {$schoolsTable} s ON d.school_id = s.id
                 WHERE s.district_id = %d
                 AND d.year = (SELECT MAX(year) FROM {$this->demographicsTable})",
                $districtId
            )
        );

        return $result !== null ? (int) $result : null;
    }

    /**
     * Get demographics for a school in a given year.
     */
    public function getDemographics(int $schoolId, int $year): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->demographicsTable} WHERE school_id = %d AND year = %d",
                $schoolId,
                $year
            )
        );

        return $result ?: null;
    }

    // ------------------------------------------------------------------
    // Batch operations (for performance)
    // ------------------------------------------------------------------

    /**
     * Batch get MCAS scores for multiple schools.
     *
     * @param int[] $schoolIds
     * @return array<int, object[]> Keyed by school_id.
     */
    public function batchGetMcasScores(array $schoolIds, int $year): array
    {
        if ($schoolIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($schoolIds), '%d'));
        $args = array_merge($schoolIds, [$year]);

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->testScoresTable}
                 WHERE school_id IN ({$placeholders}) AND year = %d
                 ORDER BY school_id, subject",
                ...$args
            )
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->school_id][] = $row;
        }

        return $grouped;
    }

    /**
     * Batch get rankings for multiple schools.
     *
     * @param int[] $schoolIds
     * @return array<int, object> Keyed by school_id.
     */
    public function batchGetRankings(array $schoolIds, int $year): array
    {
        if ($schoolIds === []) {
            return [];
        }

        $rankingsTable = $this->wpdb->prefix . 'bmn_school_rankings';
        $placeholders = implode(',', array_fill(0, count($schoolIds), '%d'));
        $args = array_merge($schoolIds, [$year]);

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$rankingsTable}
                 WHERE school_id IN ({$placeholders}) AND year = %d",
                ...$args
            )
        );

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row->school_id] = $row;
        }

        return $keyed;
    }

    /**
     * Batch get demographics for multiple schools.
     *
     * @param int[] $schoolIds
     * @return array<int, object> Keyed by school_id.
     */
    public function batchGetDemographics(array $schoolIds, int $year): array
    {
        if ($schoolIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($schoolIds), '%d'));
        $args = array_merge($schoolIds, [$year]);

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->demographicsTable}
                 WHERE school_id IN ({$placeholders}) AND year = %d",
                ...$args
            )
        );

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row->school_id] = $row;
        }

        return $keyed;
    }

    /**
     * Batch get features for multiple schools by feature type.
     *
     * @param int[] $schoolIds
     * @return array<int, object> Keyed by school_id.
     */
    public function batchGetFeatures(array $schoolIds, string $featureType): array
    {
        if ($schoolIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($schoolIds), '%d'));
        $args = array_merge($schoolIds, [$featureType]);

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->featuresTable}
                 WHERE school_id IN ({$placeholders}) AND feature_type = %s",
                ...$args
            )
        );

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row->school_id] = $row;
        }

        return $keyed;
    }
}

<?php

declare(strict_types=1);

namespace BMN\Schools\Repository;

use wpdb;

/**
 * Repository for school_rankings and district_rankings tables.
 *
 * Standalone (no base class) because it spans two tables.
 */
class SchoolRankingRepository
{
    private readonly wpdb $wpdb;
    private readonly string $rankingsTable;
    private readonly string $districtRankingsTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->rankingsTable = $wpdb->prefix . 'bmn_school_rankings';
        $this->districtRankingsTable = $wpdb->prefix . 'bmn_school_district_rankings';
    }

    // ------------------------------------------------------------------
    // School Rankings
    // ------------------------------------------------------------------

    /**
     * Store or update a school ranking.
     */
    public function storeRanking(int $schoolId, int $year, array $data): bool
    {
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->rankingsTable} WHERE school_id = %d AND year = %d",
                $schoolId,
                $year
            )
        );

        $now = current_time('mysql');
        $data['school_id'] = $schoolId;
        $data['year'] = $year;
        $data['calculated_at'] = $now;
        $data['updated_at'] = $now;

        if ($existing) {
            $result = $this->wpdb->update(
                $this->rankingsTable,
                $data,
                ['id' => $existing->id]
            );
            return $result !== false;
        }

        $data['created_at'] = $now;
        $result = $this->wpdb->insert($this->rankingsTable, $data);

        return $result !== false;
    }

    /**
     * Get a school's ranking, optionally for a specific year.
     * If no year specified, returns the latest.
     */
    public function getRanking(int $schoolId, ?int $year = null): ?object
    {
        if ($year !== null) {
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->rankingsTable} WHERE school_id = %d AND year = %d",
                    $schoolId,
                    $year
                )
            );
        } else {
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->rankingsTable} WHERE school_id = %d ORDER BY year DESC LIMIT 1",
                    $schoolId
                )
            );
        }

        return $result ?: null;
    }

    /**
     * Get top-ranked schools.
     *
     * @return object[]
     */
    public function getTopSchools(int $limit = 10, ?string $category = null, ?int $year = null): array
    {
        $sql = "SELECT r.*, s.name, s.level, s.city, s.school_type
                FROM {$this->rankingsTable} r
                INNER JOIN {$this->wpdb->prefix}bmn_schools s ON r.school_id = s.id
                WHERE r.composite_score IS NOT NULL";
        $args = [];

        if ($category !== null) {
            $sql .= ' AND r.category = %s';
            $args[] = $category;
        }

        if ($year !== null) {
            $sql .= ' AND r.year = %d';
            $args[] = $year;
        } else {
            $sql .= ' AND r.year = (SELECT MAX(year) FROM ' . $this->rankingsTable . ')';
        }

        $sql .= ' ORDER BY r.composite_score DESC LIMIT %d';
        $args[] = $limit;

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$args)
        );
    }

    /**
     * Get schools with a minimum composite score.
     *
     * @return object[]
     */
    public function getSchoolsByMinScore(float $minScore, ?int $year = null): array
    {
        $sql = "SELECT * FROM {$this->rankingsTable} WHERE composite_score >= %f";
        $args = [$minScore];

        if ($year !== null) {
            $sql .= ' AND year = %d';
            $args[] = $year;
        } else {
            $sql .= ' AND year = (SELECT MAX(year) FROM ' . $this->rankingsTable . ')';
        }

        $sql .= ' ORDER BY composite_score DESC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$args)
        );
    }

    /**
     * Get schools by letter grade (e.g., all "A" schools).
     *
     * @return object[]
     */
    public function getSchoolsByGrade(string $grade, ?int $year = null): array
    {
        $sql = "SELECT * FROM {$this->rankingsTable} WHERE letter_grade = %s";
        $args = [$grade];

        if ($year !== null) {
            $sql .= ' AND year = %d';
            $args[] = $year;
        } else {
            $sql .= ' AND year = (SELECT MAX(year) FROM ' . $this->rankingsTable . ')';
        }

        $sql .= ' ORDER BY composite_score DESC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$args)
        );
    }

    /**
     * Get the latest data year from rankings (year-rollover safe).
     */
    public function getLatestDataYear(): int
    {
        $result = $this->wpdb->get_var(
            "SELECT MAX(year) FROM {$this->rankingsTable}"
        );

        return $result !== null ? (int) $result : (int) date('Y');
    }

    /**
     * Delete all rankings for a specific year (for recalculation).
     */
    public function deleteRankingsForYear(int $year): int
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->rankingsTable} WHERE year = %d",
                $year
            )
        );

        return $result !== false ? (int) $result : 0;
    }

    // ------------------------------------------------------------------
    // District Rankings
    // ------------------------------------------------------------------

    /**
     * Store or update a district ranking.
     */
    public function storeDistrictRanking(int $districtId, int $year, array $data): bool
    {
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->districtRankingsTable} WHERE district_id = %d AND year = %d",
                $districtId,
                $year
            )
        );

        $now = current_time('mysql');
        $data['district_id'] = $districtId;
        $data['year'] = $year;
        $data['updated_at'] = $now;

        if ($existing) {
            $result = $this->wpdb->update(
                $this->districtRankingsTable,
                $data,
                ['id' => $existing->id]
            );
            return $result !== false;
        }

        $data['created_at'] = $now;
        $result = $this->wpdb->insert($this->districtRankingsTable, $data);

        return $result !== false;
    }

    /**
     * Get a district's ranking.
     */
    public function getDistrictRanking(int $districtId, ?int $year = null): ?object
    {
        if ($year !== null) {
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->districtRankingsTable} WHERE district_id = %d AND year = %d",
                    $districtId,
                    $year
                )
            );
        } else {
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->districtRankingsTable} WHERE district_id = %d ORDER BY year DESC LIMIT 1",
                    $districtId
                )
            );
        }

        return $result ?: null;
    }
}

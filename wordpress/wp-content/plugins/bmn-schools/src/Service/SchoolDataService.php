<?php

declare(strict_types=1);

namespace BMN\Schools\Service;

use BMN\Platform\Database\DatabaseService;
use BMN\Schools\Model\ImportResult;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRepository;

/**
 * Programmatic import API for school data.
 *
 * No admin UI, no file parsing. Accepts normalized arrays.
 * Data populated via CLI or v1 migration script.
 */
class SchoolDataService
{
    private readonly SchoolRepository $schoolRepo;
    private readonly SchoolDistrictRepository $districtRepo;
    private readonly SchoolDataRepository $dataRepo;
    private readonly SchoolRankingService $rankingService;
    private readonly DatabaseService $databaseService;

    public function __construct(
        SchoolRepository $schoolRepo,
        SchoolDistrictRepository $districtRepo,
        SchoolDataRepository $dataRepo,
        SchoolRankingService $rankingService,
        DatabaseService $databaseService,
    ) {
        $this->schoolRepo = $schoolRepo;
        $this->districtRepo = $districtRepo;
        $this->dataRepo = $dataRepo;
        $this->rankingService = $rankingService;
        $this->databaseService = $databaseService;
    }

    /**
     * Import schools — upsert by nces_school_id.
     *
     * @param array[] $schools Each school must have 'nces_school_id' and 'name'.
     */
    public function importSchools(array $schools): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];

        foreach ($schools as $i => $school) {
            $ncesId = $school['nces_school_id'] ?? null;
            if ($ncesId === null || ($school['name'] ?? '') === '') {
                $errorMessages[] = "Row {$i}: Missing nces_school_id or name.";
                $errors++;
                continue;
            }

            $existing = $this->schoolRepo->findByNcesId($ncesId);

            if ($existing) {
                $result = $this->schoolRepo->update((int) $existing->id, $school);
                if ($result) {
                    $updated++;
                } else {
                    $errors++;
                    $errorMessages[] = "Row {$i}: Failed to update school {$ncesId}.";
                }
            } else {
                $result = $this->schoolRepo->create($school);
                if ($result !== false) {
                    $created++;
                } else {
                    $errors++;
                    $errorMessages[] = "Row {$i}: Failed to create school {$ncesId}.";
                }
            }
        }

        return new ImportResult($created, $updated, $skipped, $errors, $errorMessages);
    }

    /**
     * Import districts — upsert by nces_district_id.
     *
     * @param array[] $districts Each must have 'nces_district_id' and 'name'.
     */
    public function importDistricts(array $districts): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];

        foreach ($districts as $i => $district) {
            $ncesId = $district['nces_district_id'] ?? null;
            if ($ncesId === null || ($district['name'] ?? '') === '') {
                $errorMessages[] = "Row {$i}: Missing nces_district_id or name.";
                $errors++;
                continue;
            }

            $existing = $this->districtRepo->findByNcesId($ncesId);

            if ($existing) {
                $result = $this->districtRepo->update((int) $existing->id, $district);
                if ($result) {
                    $updated++;
                } else {
                    $errors++;
                    $errorMessages[] = "Row {$i}: Failed to update district {$ncesId}.";
                }
            } else {
                $result = $this->districtRepo->create($district);
                if ($result !== false) {
                    $created++;
                } else {
                    $errors++;
                    $errorMessages[] = "Row {$i}: Failed to create district {$ncesId}.";
                }
            }
        }

        return new ImportResult($created, $updated, $skipped, $errors, $errorMessages);
    }

    /**
     * Import test scores — batch insert, dedup by school_id+year+grade+subject.
     *
     * @param array[] $scores Each must have 'school_id', 'subject'.
     */
    public function importTestScores(array $scores, int $year): ImportResult
    {
        $created = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];

        $table = $this->databaseService->getTable('bmn_school_test_scores');
        $rows = [];

        foreach ($scores as $i => $score) {
            if (! isset($score['school_id'], $score['subject'])) {
                $errorMessages[] = "Row {$i}: Missing school_id or subject.";
                $errors++;
                continue;
            }

            $now = current_time('mysql');
            $score['year'] = $year;
            $score['created_at'] = $score['created_at'] ?? $now;
            $score['updated_at'] = $score['updated_at'] ?? $now;
            $rows[] = $score;
        }

        if ($rows !== []) {
            try {
                $created = $this->databaseService->batchInsert($table, $rows);
            } catch (\RuntimeException $e) {
                $errors += count($rows);
                $errorMessages[] = 'Batch insert failed: ' . $e->getMessage();
            }
        }

        return new ImportResult($created, 0, $skipped, $errors, $errorMessages);
    }

    /**
     * Import features — upsert by school_id+feature_type+feature_name.
     *
     * @param array[] $features Each must have 'school_id', 'feature_type', 'feature_name'.
     */
    public function importFeatures(array $features): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];
        $wpdb = $this->databaseService->getWpdb();
        $table = $this->databaseService->getTable('bmn_school_features');

        foreach ($features as $i => $feature) {
            if (! isset($feature['school_id'], $feature['feature_type'], $feature['feature_name'])) {
                $errorMessages[] = "Row {$i}: Missing school_id, feature_type, or feature_name.";
                $errors++;
                continue;
            }

            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE school_id = %d AND feature_type = %s AND feature_name = %s",
                    $feature['school_id'],
                    $feature['feature_type'],
                    $feature['feature_name']
                )
            );

            $now = current_time('mysql');

            if ($existing) {
                $feature['updated_at'] = $now;
                $result = $wpdb->update($table, $feature, ['id' => $existing->id]);
                if ($result !== false) {
                    $updated++;
                } else {
                    $errors++;
                }
            } else {
                $feature['created_at'] = $now;
                $feature['updated_at'] = $now;
                $result = $wpdb->insert($table, $feature);
                if ($result !== false) {
                    $created++;
                } else {
                    $errors++;
                }
            }
        }

        return new ImportResult($created, $updated, $skipped, $errors, $errorMessages);
    }

    /**
     * Import demographics — upsert by school_id+year.
     *
     * @param array[] $demographics Each must have 'school_id'.
     */
    public function importDemographics(array $demographics, int $year): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];
        $wpdb = $this->databaseService->getWpdb();
        $table = $this->databaseService->getTable('bmn_school_demographics');

        foreach ($demographics as $i => $row) {
            if (! isset($row['school_id'])) {
                $errorMessages[] = "Row {$i}: Missing school_id.";
                $errors++;
                continue;
            }

            $row['year'] = $year;

            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE school_id = %d AND year = %d",
                    $row['school_id'],
                    $year
                )
            );

            $now = current_time('mysql');

            if ($existing) {
                $row['updated_at'] = $now;
                $result = $wpdb->update($table, $row, ['id' => $existing->id]);
                if ($result !== false) {
                    $updated++;
                } else {
                    $errors++;
                }
            } else {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $result = $wpdb->insert($table, $row);
                if ($result !== false) {
                    $created++;
                } else {
                    $errors++;
                }
            }
        }

        return new ImportResult($created, $updated, $skipped, $errors, $errorMessages);
    }

    /**
     * Recalculate all rankings.
     *
     * @return array{total: int, ranked: int, skipped: int}
     */
    public function recalculateRankings(?int $year = null): array
    {
        return $this->rankingService->calculateAllRankings($year);
    }

    /**
     * Get import statistics.
     *
     * @return array{schools: int, districts: int, test_scores: int, features: int, demographics: int, rankings: int}
     */
    public function getImportStats(): array
    {
        return [
            'schools' => $this->schoolRepo->count(),
            'districts' => $this->districtRepo->count(),
            'test_scores' => $this->countTable('bmn_school_test_scores'),
            'features' => $this->countTable('bmn_school_features'),
            'demographics' => $this->countTable('bmn_school_demographics'),
            'rankings' => $this->countTable('bmn_school_rankings'),
        ];
    }

    private function countTable(string $tableName): int
    {
        $wpdb = $this->databaseService->getWpdb();
        $table = $this->databaseService->getTable($tableName);

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}

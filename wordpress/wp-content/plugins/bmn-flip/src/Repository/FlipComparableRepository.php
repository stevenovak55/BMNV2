<?php

declare(strict_types=1);

namespace BMN\Flip\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_flip_comparables table.
 */
class FlipComparableRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_flip_comparables';
    }

    /**
     * JSON-encode structured fields before creating.
     */
    public function create(array $data): int|false
    {
        $jsonFields = ['adjustments'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        return parent::create($data);
    }

    /**
     * Find all comparables for an analysis, ordered by weight DESC.
     *
     * @return object[]
     */
    public function findByAnalysis(int $analysisId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE analysis_id = %d ORDER BY weight DESC",
                $analysisId
            )
        ) ?? [];
    }

    /**
     * Delete all comparables for an analysis.
     */
    public function deleteByAnalysis(int $analysisId): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE analysis_id = %d",
                $analysisId
            )
        );

        return $result !== false;
    }

    /**
     * Delete all comparables belonging to analyses of a given report.
     */
    public function deleteByReport(int $reportId): bool
    {
        $analysesTable = $this->wpdb->prefix . 'bmn_flip_analyses';

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE analysis_id IN (SELECT id FROM {$analysesTable} WHERE report_id = %d)",
                $reportId
            )
        );

        return $result !== false;
    }
}

<?php

declare(strict_types=1);

namespace BMN\Flip\Service;

use BMN\Flip\Repository\FlipAnalysisRepository;
use BMN\Flip\Repository\FlipComparableRepository;
use BMN\Flip\Repository\FlipReportRepository;
use BMN\Flip\Repository\MonitorSeenRepository;

/**
 * Manage saved analysis reports/sessions.
 */
class ReportService
{
    public function __construct(
        private readonly FlipReportRepository $reportRepo,
        private readonly FlipAnalysisRepository $analysisRepo,
        private readonly FlipComparableRepository $comparableRepo,
        private readonly MonitorSeenRepository $monitorSeenRepo,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Create a new report.
     *
     * @param int    $userId  Owner user ID.
     * @param string $name    Report name.
     * @param array  $cities  Target cities.
     * @param array  $filters Search filters.
     * @param string $type    Report type (manual, monitor).
     *
     * @return int|false Inserted report ID, or false on failure.
     */
    public function createReport(
        int $userId,
        string $name,
        array $cities = [],
        array $filters = [],
        string $type = 'manual',
    ): int|false {
        return $this->reportRepo->create([
            'user_id' => $userId,
            'name'    => $name,
            'type'    => $type,
            'status'  => 'active',
            'cities'  => $cities,
            'filters' => $filters,
        ]);
    }

    /**
     * Get a single report with summary stats (ownership check).
     *
     * @return array|null Report data with summary, or null if not found/not owned.
     */
    public function getReport(int $reportId, int $userId): ?array
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null) {
            return null;
        }

        if ((int) $report->user_id !== $userId) {
            return null;
        }

        $result = (array) $report;

        // Attach summary stats.
        $summary = $this->analysisRepo->getReportSummary($reportId);
        $result['summary'] = $summary;

        return $result;
    }

    /**
     * Get paginated reports for a user.
     *
     * @return array{reports: array, total: int}
     */
    public function getUserReports(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset  = ($page - 1) * $perPage;
        $reports = $this->reportRepo->findByUser($userId, $perPage, $offset);
        $total   = $this->reportRepo->countByUser($userId);

        return [
            'reports' => $reports,
            'total'   => $total,
        ];
    }

    /**
     * Update report name, status, or filters (ownership check).
     *
     * @param int   $reportId Report ID to update.
     * @param int   $userId   Owner user ID.
     * @param array $data     Fields to update (name, status, filters, cities, etc.).
     */
    public function updateReport(int $reportId, int $userId, array $data): bool
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null) {
            return false;
        }

        if ((int) $report->user_id !== $userId) {
            return false;
        }

        // Allow only safe fields to be updated.
        $allowed = ['name', 'status', 'filters', 'cities', 'monitor_frequency', 'notification_level'];
        $update  = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // JSON-encode array fields.
                if (is_array($value) && in_array($field, ['filters', 'cities'], true)) {
                    $value = wp_json_encode($value);
                }

                $update[$field] = $value;
            }
        }

        if ($update === []) {
            return false;
        }

        return $this->reportRepo->update($reportId, $update);
    }

    /**
     * Delete report + all analyses + all comps + monitor_seen (ownership check).
     */
    public function deleteReport(int $reportId, int $userId): bool
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null) {
            return false;
        }

        if ((int) $report->user_id !== $userId) {
            return false;
        }

        // Delete in dependency order: comps -> analyses -> monitor_seen -> report.
        $this->comparableRepo->deleteByReport($reportId);
        $this->analysisRepo->deleteByReport($reportId);
        $this->monitorSeenRepo->deleteByReport($reportId);

        return $this->reportRepo->delete($reportId);
    }

    /**
     * Toggle the favorite status of a report (ownership check).
     */
    public function toggleFavorite(int $reportId, int $userId): bool
    {
        $report = $this->reportRepo->find($reportId);

        if ($report === null) {
            return false;
        }

        if ((int) $report->user_id !== $userId) {
            return false;
        }

        return $this->reportRepo->toggleFavorite($reportId);
    }

    /**
     * Record a run against a report (increment run count, update stats).
     */
    public function recordRun(int $reportId, int $propertyCount, int $viableCount): bool
    {
        return $this->reportRepo->incrementRunCount($reportId, $propertyCount, $viableCount);
    }
}

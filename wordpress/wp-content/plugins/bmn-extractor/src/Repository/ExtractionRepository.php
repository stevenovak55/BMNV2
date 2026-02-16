<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class ExtractionRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_extractions';
    }

    /**
     * Start a new extraction run. Returns the extraction ID.
     */
    public function startRun(string $type = 'incremental', string $triggeredBy = 'cron'): int
    {
        $id = $this->create([
            'extraction_type' => $type,
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'started_at' => current_time('mysql'),
        ]);

        return (int) $id;
    }

    /**
     * Update metrics for a running extraction.
     */
    public function updateMetrics(int $id, array $metrics): bool
    {
        return $this->update($id, $metrics);
    }

    /**
     * Mark extraction as completed.
     */
    public function completeRun(int $id): bool
    {
        return $this->update($id, [
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
        ]);
    }

    /**
     * Mark extraction as failed with error message.
     */
    public function failRun(int $id, string $errorMessage): bool
    {
        return $this->update($id, [
            'status' => 'failed',
            'completed_at' => current_time('mysql'),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark extraction as paused (session limit reached).
     */
    public function pauseRun(int $id): bool
    {
        return $this->update($id, [
            'status' => 'paused',
        ]);
    }

    /**
     * Get the most recent extraction run.
     */
    public function getLastRun(): ?object
    {
        return $this->wpdb->get_row(
            "SELECT * FROM {$this->table} ORDER BY started_at DESC LIMIT 1"
        );
    }

    /**
     * Get the most recent completed run.
     */
    public function getLastCompletedRun(): ?object
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY started_at DESC LIMIT 1",
                'completed'
            )
        );
    }

    /**
     * Get the most recent paused run (for continuation).
     */
    public function getLastPausedRun(): ?object
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY started_at DESC LIMIT 1",
                'paused'
            )
        );
    }

    /**
     * Check if any extraction is currently running.
     */
    public function isRunning(): bool
    {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
                'running'
            )
        );
        return (int) $count > 0;
    }

    /**
     * Get recent extraction history.
     */
    public function getHistory(int $limit = 20): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} ORDER BY started_at DESC LIMIT %d",
                $limit
            )
        ) ?? [];
    }

    /**
     * Cleanup old extraction logs.
     */
    public function cleanupOld(int $daysToKeep = 30): int
    {
        return (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE started_at < DATE_SUB(%s, INTERVAL %d DAY)",
                current_time('mysql'),
                $daysToKeep
            )
        );
    }
}

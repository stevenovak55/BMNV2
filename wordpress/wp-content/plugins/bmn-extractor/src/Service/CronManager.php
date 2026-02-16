<?php

declare(strict_types=1);

namespace BMN\Extractor\Service;

/**
 * Manages WP-Cron schedules for the extraction pipeline.
 *
 * Three cron jobs:
 *   1. bmn_extraction_cron       — every 15 minutes, incremental sync
 *   2. bmn_extraction_cleanup    — daily, cleans expired data
 *   3. bmn_extraction_continue   — every 2 minutes, continues paused sessions
 */
final class CronManager
{
    public const HOOK_EXTRACTION = 'bmn_extraction_cron';
    public const HOOK_CLEANUP = 'bmn_extraction_cleanup';
    public const HOOK_CONTINUE = 'bmn_extraction_continue';

    public const INTERVAL_EXTRACTION = 'bmn_every_15_minutes';
    public const INTERVAL_CONTINUE = 'bmn_every_2_minutes';

    private ExtractionEngine $engine;

    public function __construct(ExtractionEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Register custom cron intervals and schedule events.
     * Call this from the plugin's boot phase.
     */
    public function register(): void
    {
        // Register custom intervals.
        add_filter('cron_schedules', [$this, 'addCronIntervals']);

        // Register cron handlers.
        add_action(self::HOOK_EXTRACTION, [$this, 'handleExtraction']);
        add_action(self::HOOK_CLEANUP, [$this, 'handleCleanup']);
        add_action(self::HOOK_CONTINUE, [$this, 'handleContinuation']);

        // Schedule events if not already scheduled.
        $this->scheduleEvents();
    }

    /**
     * Unregister all cron events.
     * Call this on plugin deactivation.
     */
    public function unregister(): void
    {
        wp_clear_scheduled_hook(self::HOOK_EXTRACTION);
        wp_clear_scheduled_hook(self::HOOK_CLEANUP);
        wp_clear_scheduled_hook(self::HOOK_CONTINUE);
    }

    /**
     * Add custom cron intervals to WordPress.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function addCronIntervals(array $schedules): array
    {
        $schedules[self::INTERVAL_EXTRACTION] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'Every 15 Minutes (BMN Extraction)',
        ];

        $schedules[self::INTERVAL_CONTINUE] = [
            'interval' => 2 * MINUTE_IN_SECONDS,
            'display' => 'Every 2 Minutes (BMN Batch Continue)',
        ];

        return $schedules;
    }

    /**
     * Handle the main extraction cron event.
     * Runs an incremental sync.
     */
    public function handleExtraction(): void
    {
        try {
            $this->engine->run(isResync: false, triggeredBy: 'cron');
        } catch (\Throwable $e) {
            error_log('BMN Extractor cron failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle daily cleanup.
     * Removes expired open houses and old extraction logs.
     */
    public function handleCleanup(): void
    {
        try {
            do_action('bmn_extraction_cleanup_run');
        } catch (\Throwable $e) {
            error_log('BMN Extractor cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle batch continuation for paused sessions.
     */
    public function handleContinuation(): void
    {
        try {
            $this->engine->run(isResync: false, triggeredBy: 'continuation');
        } catch (\Throwable $e) {
            // Not an error if there's nothing to continue.
            if (strpos($e->getMessage(), 'lock') === false) {
                error_log('BMN Extractor continuation failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Schedule cron events if not already scheduled.
     */
    private function scheduleEvents(): void
    {
        if (! wp_next_scheduled(self::HOOK_EXTRACTION)) {
            wp_schedule_event(time(), self::INTERVAL_EXTRACTION, self::HOOK_EXTRACTION);
        }

        if (! wp_next_scheduled(self::HOOK_CLEANUP)) {
            wp_schedule_event(time(), 'daily', self::HOOK_CLEANUP);
        }

        if (! wp_next_scheduled(self::HOOK_CONTINUE)) {
            wp_schedule_event(time(), self::INTERVAL_CONTINUE, self::HOOK_CONTINUE);
        }
    }
}

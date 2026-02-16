<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Service;

use BMN\Extractor\Service\CronManager;
use BMN\Extractor\Service\ExtractionEngine;
use PHPUnit\Framework\TestCase;

class CronManagerTest extends TestCase
{
    private CronManager $cron;
    private ExtractionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = $this->createMock(ExtractionEngine::class);
        $this->cron = new CronManager($this->engine);

        // Reset global state.
        $GLOBALS['wp_scheduled_events'] = [];
        $GLOBALS['wp_actions'] = [];
    }

    // ------------------------------------------------------------------
    // addCronIntervals
    // ------------------------------------------------------------------

    public function testAddCronIntervalsAddsCustomSchedules(): void
    {
        $schedules = $this->cron->addCronIntervals([]);

        $this->assertArrayHasKey(CronManager::INTERVAL_EXTRACTION, $schedules);
        $this->assertArrayHasKey(CronManager::INTERVAL_CONTINUE, $schedules);
        $this->assertSame(15 * 60, $schedules[CronManager::INTERVAL_EXTRACTION]['interval']);
        $this->assertSame(2 * 60, $schedules[CronManager::INTERVAL_CONTINUE]['interval']);
    }

    public function testAddCronIntervalsPreservesExistingSchedules(): void
    {
        $existing = ['hourly' => ['interval' => 3600, 'display' => 'Hourly']];
        $schedules = $this->cron->addCronIntervals($existing);

        $this->assertArrayHasKey('hourly', $schedules);
        $this->assertArrayHasKey(CronManager::INTERVAL_EXTRACTION, $schedules);
    }

    // ------------------------------------------------------------------
    // register
    // ------------------------------------------------------------------

    public function testRegisterSchedulesAllThreeEvents(): void
    {
        $this->cron->register();

        $this->assertArrayHasKey(CronManager::HOOK_EXTRACTION, $GLOBALS['wp_scheduled_events']);
        $this->assertArrayHasKey(CronManager::HOOK_CLEANUP, $GLOBALS['wp_scheduled_events']);
        $this->assertArrayHasKey(CronManager::HOOK_CONTINUE, $GLOBALS['wp_scheduled_events']);
    }

    public function testRegisterAddsActionHandlers(): void
    {
        $this->cron->register();

        $this->assertArrayHasKey(CronManager::HOOK_EXTRACTION, $GLOBALS['wp_actions']);
        $this->assertArrayHasKey(CronManager::HOOK_CLEANUP, $GLOBALS['wp_actions']);
        $this->assertArrayHasKey(CronManager::HOOK_CONTINUE, $GLOBALS['wp_actions']);
    }

    // ------------------------------------------------------------------
    // unregister
    // ------------------------------------------------------------------

    public function testUnregisterClearsAllScheduledHooks(): void
    {
        $GLOBALS['wp_scheduled_events'] = [
            CronManager::HOOK_EXTRACTION => time(),
            CronManager::HOOK_CLEANUP => time(),
            CronManager::HOOK_CONTINUE => time(),
        ];

        $this->cron->unregister();

        $this->assertArrayNotHasKey(CronManager::HOOK_EXTRACTION, $GLOBALS['wp_scheduled_events']);
        $this->assertArrayNotHasKey(CronManager::HOOK_CLEANUP, $GLOBALS['wp_scheduled_events']);
        $this->assertArrayNotHasKey(CronManager::HOOK_CONTINUE, $GLOBALS['wp_scheduled_events']);
    }

    // ------------------------------------------------------------------
    // handleExtraction
    // ------------------------------------------------------------------

    public function testHandleExtractionCallsEngineRun(): void
    {
        $this->engine->expects($this->once())
            ->method('run')
            ->with(false, 'cron');

        $this->cron->handleExtraction();
    }

    // ------------------------------------------------------------------
    // handleContinuation
    // ------------------------------------------------------------------

    public function testHandleContinuationCallsEngineWithContinuation(): void
    {
        $this->engine->expects($this->once())
            ->method('run')
            ->with(false, 'continuation');

        $this->cron->handleContinuation();
    }

    // ------------------------------------------------------------------
    // scheduleEvents — skip already scheduled
    // ------------------------------------------------------------------

    public function testScheduleEventsSkipsAlreadyScheduled(): void
    {
        // Pre-set a scheduled event.
        $GLOBALS['wp_scheduled_events'] = [
            CronManager::HOOK_EXTRACTION => 999999,
        ];

        $this->cron->register();

        // The already-scheduled hook should keep its original timestamp.
        $this->assertSame(999999, $GLOBALS['wp_scheduled_events'][CronManager::HOOK_EXTRACTION]);
    }
}

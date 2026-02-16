<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Admin;

use BMN\Extractor\Admin\AdminDashboard;
use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\PropertyRepository;
use BMN\Extractor\Service\ExtractionEngine;
use PHPUnit\Framework\TestCase;

class AdminDashboardTest extends TestCase
{
    private AdminDashboard $dashboard;
    private ExtractionEngine $engine;
    private ExtractionRepository $extractions;
    private PropertyRepository $properties;

    protected function setUp(): void
    {
        $this->engine = $this->createMock(ExtractionEngine::class);
        $this->extractions = $this->createMock(ExtractionRepository::class);
        $this->properties = $this->createMock(PropertyRepository::class);

        $this->dashboard = new AdminDashboard(
            $this->engine,
            $this->extractions,
            $this->properties,
        );

        // Reset globals.
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_json_response'] = null;
        $GLOBALS['wp_current_user_can'] = true;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_current_user_can']);
        unset($GLOBALS['wp_json_response']);
    }

    // ------------------------------------------------------------------
    // register()
    // ------------------------------------------------------------------

    public function testRegisterAddsMenuPageAction(): void
    {
        $this->dashboard->register();

        $this->assertArrayHasKey('admin_menu', $GLOBALS['wp_actions']);
    }

    public function testRegisterAddsEnqueueScriptsAction(): void
    {
        $this->dashboard->register();

        $this->assertArrayHasKey('admin_enqueue_scripts', $GLOBALS['wp_actions']);
    }

    public function testRegisterAddsAjaxActions(): void
    {
        $this->dashboard->register();

        $this->assertArrayHasKey('wp_ajax_bmn_extraction_status', $GLOBALS['wp_actions']);
        $this->assertArrayHasKey('wp_ajax_bmn_extraction_trigger', $GLOBALS['wp_actions']);
        $this->assertArrayHasKey('wp_ajax_bmn_extraction_history', $GLOBALS['wp_actions']);
    }

    // ------------------------------------------------------------------
    // ajaxStatus
    // ------------------------------------------------------------------

    public function testAjaxStatusReturnsCorrectStructure(): void
    {
        $this->extractions->method('getLastRun')->willReturn(
            (object) [
                'status' => 'completed',
                'listings_processed' => '100',
                'started_at' => '2026-02-15 10:00:00',
                'completed_at' => '2026-02-15 10:05:00',
            ]
        );
        $this->extractions->method('isRunning')->willReturn(false);
        $this->properties->method('countByStatus')->willReturn(['Active' => 50]);

        $this->dashboard->ajaxStatus();

        $response = $GLOBALS['wp_json_response'];
        $this->assertTrue($response['success']);
        $this->assertFalse($response['data']['is_running']);
        $this->assertSame(50, $response['data']['total_properties']);
        $this->assertArrayHasKey('last_run', $response['data']);
    }

    // ------------------------------------------------------------------
    // ajaxTrigger
    // ------------------------------------------------------------------

    public function testAjaxTriggerChecksPermissions(): void
    {
        $GLOBALS['wp_current_user_can'] = false;

        $this->dashboard->ajaxTrigger();

        $response = $GLOBALS['wp_json_response'];
        $this->assertFalse($response['success']);
    }

    public function testAjaxTriggerRunsExtraction(): void
    {
        $this->engine->method('run')->willReturn([
            'extraction_id' => 1,
            'status' => 'completed',
            'processed' => 50,
        ]);

        $this->dashboard->ajaxTrigger();

        $response = $GLOBALS['wp_json_response'];
        $this->assertTrue($response['success']);
        $this->assertSame(1, $response['data']['extraction_id']);
    }

    // ------------------------------------------------------------------
    // ajaxHistory
    // ------------------------------------------------------------------

    public function testAjaxHistoryReturnsRunList(): void
    {
        $this->extractions->method('getHistory')
            ->willReturn([
                (object) ['id' => 1, 'status' => 'completed'],
            ]);

        $this->dashboard->ajaxHistory();

        $response = $GLOBALS['wp_json_response'];
        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']);
    }
}

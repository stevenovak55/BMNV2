<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Api;

use BMN\Extractor\Api\Controllers\ExtractionController;
use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\PropertyRepository;
use BMN\Extractor\Service\ExtractionEngine;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class ExtractionControllerTest extends TestCase
{
    private ExtractionEngine $engine;
    private ExtractionRepository $extractions;
    private PropertyRepository $properties;
    private ExtractionController $controller;

    protected function setUp(): void
    {
        $this->engine = $this->createMock(ExtractionEngine::class);
        $this->extractions = $this->createMock(ExtractionRepository::class);
        $this->properties = $this->createMock(PropertyRepository::class);

        $this->controller = new ExtractionController(
            $this->engine,
            $this->extractions,
            $this->properties,
        );

        // Default: admin user.
        $GLOBALS['wp_current_user_can'] = true;
        $GLOBALS['wp_rest_routes'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_current_user_can']);
    }

    // ------------------------------------------------------------------
    // getRoutes / registerRoutes
    // ------------------------------------------------------------------

    public function testRegisterRoutesRegisters4Endpoints(): void
    {
        $this->controller->registerRoutes();

        $routes = $GLOBALS['wp_rest_routes'] ?? [];
        $this->assertArrayHasKey('bmn/v1/extractions/status', $routes);
        $this->assertArrayHasKey('bmn/v1/extractions/trigger', $routes);
        $this->assertArrayHasKey('bmn/v1/extractions/history', $routes);
        $this->assertArrayHasKey('bmn/v1/extractions/stats', $routes);
    }

    public function testStatusRouteIsGet(): void
    {
        $this->controller->registerRoutes();
        $route = $GLOBALS['wp_rest_routes']['bmn/v1/extractions/status'];
        $this->assertSame('GET', $route['methods']);
    }

    public function testTriggerRouteIsPost(): void
    {
        $this->controller->registerRoutes();
        $route = $GLOBALS['wp_rest_routes']['bmn/v1/extractions/trigger'];
        $this->assertSame('POST', $route['methods']);
    }

    // ------------------------------------------------------------------
    // status()
    // ------------------------------------------------------------------

    public function testStatusReturnsIsRunningAndLastRunData(): void
    {
        $lastRun = (object) [
            'id' => 1,
            'extraction_type' => 'incremental',
            'status' => 'completed',
            'triggered_by' => 'cron',
            'listings_processed' => '100',
            'listings_created' => '80',
            'listings_updated' => '20',
            'errors_count' => '0',
            'started_at' => '2026-02-15 10:00:00',
            'completed_at' => '2026-02-15 10:05:00',
        ];

        $this->extractions->method('getLastRun')->willReturn($lastRun);
        $this->extractions->method('isRunning')->willReturn(false);

        $request = new WP_REST_Request('GET', '/bmn/v1/extractions/status');
        $response = $this->controller->status($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertFalse($data['data']['is_running']);
        $this->assertSame(100, $data['data']['last_run']['listings_processed']);
    }

    public function testStatusReturnsNullLastRunWhenNone(): void
    {
        $this->extractions->method('getLastRun')->willReturn(null);
        $this->extractions->method('isRunning')->willReturn(false);

        $request = new WP_REST_Request('GET', '/bmn/v1/extractions/status');
        $response = $this->controller->status($request);

        $data = $response->get_data();
        $this->assertNull($data['data']['last_run']);
    }

    // ------------------------------------------------------------------
    // trigger()
    // ------------------------------------------------------------------

    public function testTriggerChecksPermissions(): void
    {
        $GLOBALS['wp_current_user_can'] = false;

        $request = new WP_REST_Request('POST', '/bmn/v1/extractions/trigger');
        $response = $this->controller->trigger($request);

        $this->assertSame(403, $response->get_status());
    }

    public function testTriggerRunsEngineAndReturnsResult(): void
    {
        $this->engine->method('run')->willReturn([
            'extraction_id' => 42,
            'status' => 'completed',
            'processed' => 100,
            'created' => 80,
            'updated' => 20,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/extractions/trigger');
        $response = $this->controller->trigger($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(42, $data['data']['extraction_id']);
        $this->assertSame(100, $data['data']['processed']);
    }

    public function testTriggerReturnsErrorOnException(): void
    {
        $this->engine->method('run')
            ->willThrowException(new \RuntimeException('Lock failed'));

        $request = new WP_REST_Request('POST', '/bmn/v1/extractions/trigger');
        $response = $this->controller->trigger($request);

        $this->assertSame(500, $response->get_status());
    }

    // ------------------------------------------------------------------
    // history()
    // ------------------------------------------------------------------

    public function testHistoryReturnsFormattedRuns(): void
    {
        $runs = [
            (object) [
                'id' => 2,
                'extraction_type' => 'incremental',
                'status' => 'completed',
                'triggered_by' => 'cron',
                'listings_processed' => '50',
                'listings_created' => '40',
                'listings_updated' => '10',
                'errors_count' => '0',
                'started_at' => '2026-02-15 10:00:00',
                'completed_at' => '2026-02-15 10:02:00',
                'error_message' => null,
            ],
        ];
        $this->extractions->method('getHistory')->willReturn($runs);

        $request = new WP_REST_Request('GET', '/bmn/v1/extractions/history');
        $response = $this->controller->history($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(2, $data['data'][0]['id']);
        $this->assertSame(50, $data['data'][0]['listings_processed']);
    }

    // ------------------------------------------------------------------
    // stats()
    // ------------------------------------------------------------------

    public function testStatsReturnsPropertyCountsAndLastModification(): void
    {
        $this->properties->method('countByStatus')->willReturn([
            'Active' => 150,
            'Closed' => 50,
        ]);
        $this->properties->method('getLastModificationTimestamp')
            ->willReturn('2026-02-15 10:00:00');

        $request = new WP_REST_Request('GET', '/bmn/v1/extractions/stats');
        $response = $this->controller->stats($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(200, $data['data']['total_properties']);
        $this->assertSame(150, $data['data']['by_status']['Active']);
        $this->assertSame('2026-02-15 10:00:00', $data['data']['last_modification']);
    }
}

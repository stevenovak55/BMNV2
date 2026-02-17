<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Controller;

use BMN\Flip\Controller\FlipController;
use BMN\Flip\Service\ArvService;
use BMN\Flip\Service\FlipAnalysisService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class FlipControllerTest extends TestCase
{
    private FlipController $controller;
    private FlipAnalysisService&MockObject $analysisService;
    private ArvService&MockObject $arvService;

    protected function setUp(): void
    {
        $this->analysisService = $this->createMock(FlipAnalysisService::class);
        $this->arvService = $this->createMock(ArvService::class);

        // AuthMiddleware is final — create real instance with mocked AuthService.
        $authService = $this->createMock(AuthService::class);
        $authMiddleware = new AuthMiddleware($authService);

        $this->controller = new FlipController(
            $this->analysisService,
            $this->arvService,
            $authMiddleware,
        );

        // Set up authenticated user.
        wp_set_current_user(42, 'testuser');

        $GLOBALS['wp_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['current_user']);
        $GLOBALS['wp_options'] = [];
    }

    // ------------------------------------------------------------------
    // analyzeProperty
    // ------------------------------------------------------------------

    public function testAnalyzePropertySuccess(): void
    {
        $this->analysisService->method('analyzeProperty')->willReturn([
            'listing_id'    => 'MLS123',
            'estimated_arv' => 650000,
            'cash_profit'   => 75000,
            'total_score'   => 72.5,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/flip/analyze');
        $request->set_param('listing_id', 'MLS123');
        $request->set_param('list_price', 500000);
        $request->set_param('latitude', 42.3601);
        $request->set_param('longitude', -71.0589);

        $response = $this->controller->analyzeProperty($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('MLS123', $data['data']['listing_id']);
        $this->assertSame(650000, $data['data']['estimated_arv']);
    }

    public function testAnalyzePropertyMissingListingId(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/flip/analyze');
        $request->set_param('list_price', 500000);
        $request->set_param('latitude', 42.3601);
        $request->set_param('longitude', -71.0589);

        $response = $this->controller->analyzeProperty($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('listing_id', $data['meta']['error']);
    }

    public function testAnalyzePropertyMissingCoordinates(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/flip/analyze');
        $request->set_param('listing_id', 'MLS123');
        $request->set_param('list_price', 500000);

        $response = $this->controller->analyzeProperty($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Latitude', $data['meta']['error']);
    }

    public function testAnalyzePropertyUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/bmn/v1/flip/analyze');
        $request->set_param('listing_id', 'MLS123');
        $request->set_param('list_price', 500000);
        $request->set_param('latitude', 42.3601);
        $request->set_param('longitude', -71.0589);

        $response = $this->controller->analyzeProperty($request);

        $this->assertSame(401, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    // ------------------------------------------------------------------
    // listResults
    // ------------------------------------------------------------------

    public function testListResultsSuccess(): void
    {
        $this->analysisService->method('getAnalysesByReport')->willReturn([
            'analyses' => [
                (object) ['id' => 1, 'listing_id' => 'MLS100'],
                (object) ['id' => 2, 'listing_id' => 'MLS200'],
            ],
            'total' => 25,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/results');
        $request->set_param('report_id', 10);
        $request->set_param('page', 1);
        $request->set_param('per_page', 50);

        $response = $this->controller->listResults($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(25, $data['meta']['total']);
    }

    public function testListResultsMissingReportId(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/flip/results');

        $response = $this->controller->listResults($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('report_id', $data['meta']['error']);
    }

    // ------------------------------------------------------------------
    // getResult
    // ------------------------------------------------------------------

    public function testGetResultSuccess(): void
    {
        $this->analysisService->method('getAnalysis')->willReturn([
            'id'            => 5,
            'listing_id'    => 'MLS500',
            'estimated_arv' => 700000,
            'comparables'   => [],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/results/5');
        $request->set_param('id', 5);

        $response = $this->controller->getResult($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('MLS500', $data['data']['listing_id']);
    }

    public function testGetResultNotFound(): void
    {
        $this->analysisService->method('getAnalysis')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/results/999');
        $request->set_param('id', 999);

        $response = $this->controller->getResult($request);

        $this->assertSame(404, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    // ------------------------------------------------------------------
    // getComps
    // ------------------------------------------------------------------

    public function testGetCompsSuccess(): void
    {
        $comps = [
            ['listing_id' => 'COMP1', 'adjusted_price' => 680000],
            ['listing_id' => 'COMP2', 'adjusted_price' => 710000],
        ];

        $this->analysisService->method('getAnalysis')->willReturn([
            'id'            => 5,
            'listing_id'    => 'MLS500',
            'comparables'   => $comps,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/results/5/comps');
        $request->set_param('id', 5);

        $response = $this->controller->getComps($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame('COMP1', $data['data'][0]['listing_id']);
    }

    // ------------------------------------------------------------------
    // getSummary
    // ------------------------------------------------------------------

    public function testGetSummarySuccess(): void
    {
        $this->analysisService->method('getReportSummary')->willReturn([
            'Boston'    => ['count' => 10, 'avg_score' => 65.0],
            'Cambridge' => ['count' => 5, 'avg_score' => 72.0],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/summary');
        $request->set_param('report_id', 10);

        $response = $this->controller->getSummary($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('Boston', $data['data']);
        $this->assertSame(10, $data['data']['Boston']['count']);
    }

    // ------------------------------------------------------------------
    // calculateArv
    // ------------------------------------------------------------------

    public function testCalculateArvSuccess(): void
    {
        $this->arvService->method('calculateArv')->willReturn([
            'arv'              => 625000.0,
            'confidence'       => 'medium',
            'confidence_score' => 55.0,
            'comp_count'       => 6,
            'avg_ppsf'         => 350.0,
            'comparables'      => [],
            'neighborhood_ceiling' => 750000.0,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/flip/arv');
        $request->set_param('listing_id', 'MLS123');
        $request->set_param('latitude', 42.3601);
        $request->set_param('longitude', -71.0589);

        $response = $this->controller->calculateArv($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(625000.0, $data['data']['arv']);
        $this->assertSame(6, $data['data']['comp_count']);
    }

    public function testCalculateArvMissingCoords(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/flip/arv');
        $request->set_param('listing_id', 'MLS123');

        $response = $this->controller->calculateArv($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Latitude', $data['meta']['error']);
    }

    // ------------------------------------------------------------------
    // getCities / setCities
    // ------------------------------------------------------------------

    public function testGetCities(): void
    {
        $GLOBALS['wp_options']['bmn_flip_target_cities'] = ['Boston', 'Cambridge', 'Somerville'];

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/config/cities');

        $response = $this->controller->getCities($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(3, $data['data']);
        $this->assertSame('Boston', $data['data'][0]);
    }

    public function testSetCities(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/flip/config/cities');
        $request->set_param('cities', ['Brookline', 'Newton']);

        $response = $this->controller->setCities($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['updated']);

        // Verify the option was stored.
        $this->assertSame(
            ['Brookline', 'Newton'],
            $GLOBALS['wp_options']['bmn_flip_target_cities'],
        );
    }
}

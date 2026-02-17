<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Controller;

use BMN\CMA\Controller\CmaController;
use BMN\CMA\Service\CmaReportService;
use BMN\CMA\Service\ComparableSearchService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class CmaControllerTest extends TestCase
{
    private CmaReportService&MockObject $reportService;
    private ComparableSearchService&MockObject $searchService;
    private CmaController $controller;

    protected function setUp(): void
    {
        $this->reportService = $this->createMock(CmaReportService::class);
        $this->searchService = $this->createMock(ComparableSearchService::class);
        $this->controller = new CmaController($this->reportService, $this->searchService, null);
    }

    private function setCurrentUser(int $id): void
    {
        wp_set_current_user($id);
    }

    private function clearCurrentUser(): void
    {
        unset($GLOBALS['current_user']);
    }

    protected function tearDown(): void
    {
        $this->clearCurrentUser();
    }

    // ------------------------------------------------------------------
    // generate
    // ------------------------------------------------------------------

    public function testGenerateReturns401WhenNotAuthenticated(): void
    {
        $this->clearCurrentUser();
        $request = new WP_REST_Request('POST', '/bmn/v1/cma');

        $response = $this->controller->generate($request);

        $this->assertSame(401, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function testGenerateReturns422WhenMissingListingIdAndAddress(): void
    {
        $this->setCurrentUser(42);
        $request = new WP_REST_Request('POST', '/bmn/v1/cma');

        $response = $this->controller->generate($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('listing_id or subject address', $data['meta']['error']);
    }

    public function testGenerateReturns422WhenMissingCoordinates(): void
    {
        $this->setCurrentUser(42);
        $request = new WP_REST_Request('POST', '/bmn/v1/cma');
        $request->set_param('listing_id', 'MLS100');

        $response = $this->controller->generate($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('latitude and longitude', $data['meta']['error']);
    }

    public function testGenerateReturnsSuccessWithValidData(): void
    {
        $this->setCurrentUser(42);

        $this->reportService->method('generateReport')->willReturn([
            'report_id'   => 1,
            'subject'     => [],
            'comparables' => [],
            'valuation'   => ['low' => 450000, 'mid' => 500000, 'high' => 550000],
            'confidence'  => ['score' => 75.0, 'level' => 'medium'],
            'statistics'  => [],
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/cma');
        $request->set_param('listing_id', 'MLS100');
        $request->set_param('latitude', 42.36);
        $request->set_param('longitude', -71.05);

        $response = $this->controller->generate($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['data']['report_id']);
    }

    public function testGenerateReturns500OnRuntimeException(): void
    {
        $this->setCurrentUser(42);

        $this->reportService->method('generateReport')
            ->willThrowException(new \RuntimeException('Failed to create CMA report.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/cma');
        $request->set_param('listing_id', 'MLS100');
        $request->set_param('latitude', 42.36);
        $request->set_param('longitude', -71.05);

        $response = $this->controller->generate($request);

        $this->assertSame(500, $response->get_status());
    }

    // ------------------------------------------------------------------
    // listSessions
    // ------------------------------------------------------------------

    public function testListSessionsReturns401WhenNotAuthenticated(): void
    {
        $this->clearCurrentUser();
        $request = new WP_REST_Request('GET', '/bmn/v1/cma/sessions');

        $response = $this->controller->listSessions($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testListSessionsReturnsPaginatedResponse(): void
    {
        $this->setCurrentUser(42);

        $this->reportService->method('getUserReports')->willReturn([
            'reports' => [(object) ['id' => 1], (object) ['id' => 2]],
            'total'   => 50,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/sessions');
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);

        $response = $this->controller->listSessions($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(50, $data['meta']['total']);
    }

    // ------------------------------------------------------------------
    // getSession
    // ------------------------------------------------------------------

    public function testGetSessionReturns404WhenNotFound(): void
    {
        $this->setCurrentUser(42);
        $this->reportService->method('getReport')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/sessions/999');
        $request->set_param('id', 999);

        $response = $this->controller->getSession($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testGetSessionReturnsSuccessWhenFound(): void
    {
        $this->setCurrentUser(42);
        $this->reportService->method('getReport')->willReturn([
            'report'      => (object) ['id' => 1],
            'comparables' => [],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/sessions/1');
        $request->set_param('id', 1);

        $response = $this->controller->getSession($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    // ------------------------------------------------------------------
    // updateSession
    // ------------------------------------------------------------------

    public function testUpdateSessionReturns422WhenNoFields(): void
    {
        $this->setCurrentUser(42);

        $request = new WP_REST_Request('PUT', '/bmn/v1/cma/sessions/1');
        $request->set_param('id', 1);

        $response = $this->controller->updateSession($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('No updatable fields', $data['meta']['error']);
    }

    public function testUpdateSessionReturnsSuccessWhenUpdated(): void
    {
        $this->setCurrentUser(42);
        $this->reportService->method('updateReport')->willReturn(true);

        $request = new WP_REST_Request('PUT', '/bmn/v1/cma/sessions/1');
        $request->set_param('id', 1);
        $request->set_param('session_name', 'My Updated CMA');

        $response = $this->controller->updateSession($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['data']['updated']);
    }

    // ------------------------------------------------------------------
    // deleteSession
    // ------------------------------------------------------------------

    public function testDeleteSessionReturns404WhenNotFound(): void
    {
        $this->setCurrentUser(42);
        $this->reportService->method('deleteReport')->willReturn(false);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/cma/sessions/999');
        $request->set_param('id', 999);

        $response = $this->controller->deleteSession($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testDeleteSessionReturnsSuccessWhenDeleted(): void
    {
        $this->setCurrentUser(42);
        $this->reportService->method('deleteReport')->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/cma/sessions/1');
        $request->set_param('id', 1);

        $response = $this->controller->deleteSession($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['data']['deleted']);
    }

    // ------------------------------------------------------------------
    // toggleFavorite
    // ------------------------------------------------------------------

    public function testToggleFavoriteReturns404WhenNotFound(): void
    {
        $this->setCurrentUser(42);
        $this->reportService->method('toggleFavorite')->willReturn(false);

        $request = new WP_REST_Request('POST', '/bmn/v1/cma/sessions/999/favorite');
        $request->set_param('id', 999);

        $response = $this->controller->toggleFavorite($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testToggleFavoriteReturnsSuccessWhenToggled(): void
    {
        $this->setCurrentUser(42);
        $this->reportService->method('toggleFavorite')->willReturn(true);

        $request = new WP_REST_Request('POST', '/bmn/v1/cma/sessions/1/favorite');
        $request->set_param('id', 1);

        $response = $this->controller->toggleFavorite($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['data']['toggled']);
    }

    // ------------------------------------------------------------------
    // findComparables
    // ------------------------------------------------------------------

    public function testFindComparablesReturns422WhenMissingCoordinates(): void
    {
        $this->setCurrentUser(42);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/comparables/MLS100');
        $request->set_param('listing_id', 'MLS100');

        $response = $this->controller->findComparables($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testFindComparablesReturnsSuccessWithComps(): void
    {
        $this->setCurrentUser(42);

        $comp = (object) ['listing_id' => 'MLS200'];
        $this->searchService->method('findComparables')->willReturn([$comp]);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/comparables/MLS100');
        $request->set_param('listing_id', 'MLS100');
        $request->set_param('latitude', 42.36);
        $request->set_param('longitude', -71.05);

        $response = $this->controller->findComparables($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(1, $data['data']['count']);
    }

    // ------------------------------------------------------------------
    // getPropertyHistory
    // ------------------------------------------------------------------

    public function testGetPropertyHistoryReturnsSuccess(): void
    {
        $this->setCurrentUser(42);

        $history = [(object) ['id' => 1]];
        $this->reportService->method('getPropertyHistory')->willReturn($history);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/history/MLS100');
        $request->set_param('listing_id', 'MLS100');

        $response = $this->controller->getPropertyHistory($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('MLS100', $data['data']['listing_id']);
        $this->assertCount(1, $data['data']['history']);
    }

    // ------------------------------------------------------------------
    // getValueTrends
    // ------------------------------------------------------------------

    public function testGetValueTrendsReturns422WhenMissingListingId(): void
    {
        $this->setCurrentUser(42);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/history/trends');

        $response = $this->controller->getValueTrends($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testGetValueTrendsReturnsSuccess(): void
    {
        $this->setCurrentUser(42);

        $trends = [(object) ['id' => 1]];
        $this->reportService->method('getValueTrends')->willReturn($trends);

        $request = new WP_REST_Request('GET', '/bmn/v1/cma/history/trends');
        $request->set_param('listing_id', 'MLS100');

        $response = $this->controller->getValueTrends($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('MLS100', $data['data']['listing_id']);
        $this->assertCount(1, $data['data']['trends']);
    }
}

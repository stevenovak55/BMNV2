<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Controller;

use BMN\Flip\Controller\ReportController;
use BMN\Flip\Service\ReportService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class ReportControllerTest extends TestCase
{
    private ReportController $controller;
    private ReportService&MockObject $reportService;

    protected function setUp(): void
    {
        $this->reportService = $this->createMock(ReportService::class);

        // AuthMiddleware is final — create real instance with mocked AuthService.
        $authService = $this->createMock(AuthService::class);
        $authMiddleware = new AuthMiddleware($authService);

        $this->controller = new ReportController(
            $this->reportService,
            $authMiddleware,
        );

        // Set up authenticated user.
        wp_set_current_user(42, 'testuser');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['current_user']);
    }

    // ------------------------------------------------------------------
    // listReports
    // ------------------------------------------------------------------

    public function testListReportsSuccess(): void
    {
        $this->reportService->method('getUserReports')->willReturn([
            'reports' => [
                (object) ['id' => 1, 'name' => 'Boston Flips'],
                (object) ['id' => 2, 'name' => 'Cambridge Flips'],
            ],
            'total' => 30,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/reports');
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);

        $response = $this->controller->listReports($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(30, $data['meta']['total']);
    }

    public function testListReportsUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/reports');

        $response = $this->controller->listReports($request);

        $this->assertSame(401, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    // ------------------------------------------------------------------
    // createReport
    // ------------------------------------------------------------------

    public function testCreateReportSuccess(): void
    {
        $this->reportService->method('createReport')->willReturn(99);

        $request = new WP_REST_Request('POST', '/bmn/v1/flip/reports');
        $request->set_param('name', 'My New Report');
        $request->set_param('cities', ['Boston', 'Cambridge']);
        $request->set_param('type', 'manual');

        $response = $this->controller->createReport($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(99, $data['data']['report_id']);
    }

    public function testCreateReportMissingName(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/flip/reports');
        $request->set_param('cities', ['Boston']);

        $response = $this->controller->createReport($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('name', $data['meta']['error']);
    }

    // ------------------------------------------------------------------
    // getReport
    // ------------------------------------------------------------------

    public function testGetReportSuccess(): void
    {
        $this->reportService->method('getReport')->willReturn([
            'id'      => 10,
            'name'    => 'Boston Flips',
            'summary' => ['count' => 15],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/reports/10');
        $request->set_param('id', 10);

        $response = $this->controller->getReport($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('Boston Flips', $data['data']['name']);
    }

    public function testGetReportNotFound(): void
    {
        $this->reportService->method('getReport')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/flip/reports/999');
        $request->set_param('id', 999);

        $response = $this->controller->getReport($request);

        $this->assertSame(404, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    // ------------------------------------------------------------------
    // updateReport
    // ------------------------------------------------------------------

    public function testUpdateReportSuccess(): void
    {
        $this->reportService->method('updateReport')->willReturn(true);

        $request = new WP_REST_Request('PUT', '/bmn/v1/flip/reports/10');
        $request->set_param('id', 10);
        $request->set_param('name', 'Updated Report Name');

        $response = $this->controller->updateReport($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['updated']);
    }

    public function testUpdateReportNoFields(): void
    {
        $request = new WP_REST_Request('PUT', '/bmn/v1/flip/reports/10');
        $request->set_param('id', 10);

        $response = $this->controller->updateReport($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('No updatable fields', $data['meta']['error']);
    }

    // ------------------------------------------------------------------
    // deleteReport
    // ------------------------------------------------------------------

    public function testDeleteReportSuccess(): void
    {
        $this->reportService->method('deleteReport')->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/flip/reports/10');
        $request->set_param('id', 10);

        $response = $this->controller->deleteReport($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['deleted']);
    }

    // ------------------------------------------------------------------
    // toggleFavorite
    // ------------------------------------------------------------------

    public function testToggleFavoriteSuccess(): void
    {
        $this->reportService->method('toggleFavorite')->willReturn(true);

        $request = new WP_REST_Request('POST', '/bmn/v1/flip/reports/10/favorite');
        $request->set_param('id', 10);

        $response = $this->controller->toggleFavorite($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['toggled']);
    }
}

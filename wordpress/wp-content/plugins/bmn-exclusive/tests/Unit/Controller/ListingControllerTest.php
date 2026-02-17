<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Controller;

use BMN\Exclusive\Api\Controllers\ListingController;
use BMN\Exclusive\Service\ListingService;
use BMN\Exclusive\Service\ValidationService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class ListingControllerTest extends TestCase
{
    private ListingController $controller;
    private ListingService&MockObject $listingService;
    private ValidationService&MockObject $validator;

    protected function setUp(): void
    {
        $this->listingService = $this->createMock(ListingService::class);
        $this->validator = $this->createMock(ValidationService::class);

        $authService = $this->createMock(AuthService::class);
        $authMiddleware = new AuthMiddleware($authService);

        $this->controller = new ListingController(
            $this->listingService,
            $this->validator,
            $authMiddleware,
        );

        wp_set_current_user(42, 'testuser');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['current_user']);
    }

    // -- listListings --

    public function testListListingsSuccess(): void
    {
        $this->listingService->method('getAgentListings')->willReturn([
            'listings' => [
                (object) ['id' => 1, 'city' => 'Boston'],
                (object) ['id' => 2, 'city' => 'Cambridge'],
            ],
            'total' => 50,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive');
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);

        $response = $this->controller->listListings($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(50, $data['meta']['total']);
    }

    public function testListListingsUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive');
        $response = $this->controller->listListings($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testListListingsPassesStatusFilter(): void
    {
        $this->listingService->expects($this->once())
            ->method('getAgentListings')
            ->with(42, 1, 20, 'active')
            ->willReturn(['listings' => [], 'total' => 0]);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive');
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);
        $request->set_param('status', 'active');

        $this->controller->listListings($request);
    }

    // -- createListing --

    public function testCreateListingSuccess(): void
    {
        $this->listingService->method('createListing')->willReturn([
            'success' => true,
            'listing_id' => 10,
            'listing_number' => 1,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/exclusive');
        $request->set_body(json_encode([
            'property_type' => 'Residential',
            'list_price' => 500000,
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->controller->createListing($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(10, $data['data']['listing_id']);
    }

    public function testCreateListingValidationError(): void
    {
        $this->listingService->method('createListing')->willReturn([
            'success' => false,
            'errors' => ['property_type' => 'Property type is required.'],
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/exclusive');
        $request->set_body(json_encode([]));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->controller->createListing($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('required', $data['meta']['error']);
    }

    public function testCreateListingUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/bmn/v1/exclusive');
        $response = $this->controller->createListing($request);

        $this->assertSame(401, $response->get_status());
    }

    // -- getListing --

    public function testGetListingSuccess(): void
    {
        $this->listingService->method('getListing')->willReturn([
            'id' => 1,
            'city' => 'Boston',
            'photos' => [],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive/1');
        $request->set_param('id', 1);

        $response = $this->controller->getListing($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('Boston', $data['data']['city']);
    }

    public function testGetListingNotFound(): void
    {
        $this->listingService->method('getListing')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive/999');
        $request->set_param('id', 999);

        $response = $this->controller->getListing($request);

        $this->assertSame(404, $response->get_status());
    }

    // -- updateListing --

    public function testUpdateListingSuccess(): void
    {
        $this->listingService->method('updateListing')->willReturn([
            'success' => true,
            'updated' => true,
        ]);

        $request = new WP_REST_Request('PUT', '/bmn/v1/exclusive/1');
        $request->set_param('id', 1);
        $request->set_body(json_encode(['city' => 'Cambridge']));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->controller->updateListing($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testUpdateListingError(): void
    {
        $this->listingService->method('updateListing')->willReturn([
            'success' => false,
            'errors' => ['listing' => 'Listing not found or access denied.'],
        ]);

        $request = new WP_REST_Request('PUT', '/bmn/v1/exclusive/999');
        $request->set_param('id', 999);
        $request->set_body(json_encode(['city' => 'Cambridge']));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->controller->updateListing($request);

        $this->assertSame(422, $response->get_status());
    }

    // -- deleteListing --

    public function testDeleteListingSuccess(): void
    {
        $this->listingService->method('deleteListing')->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/exclusive/1');
        $request->set_param('id', 1);

        $response = $this->controller->deleteListing($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['data']['deleted']);
    }

    public function testDeleteListingNotFound(): void
    {
        $this->listingService->method('deleteListing')->willReturn(false);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/exclusive/999');
        $request->set_param('id', 999);

        $response = $this->controller->deleteListing($request);

        $this->assertSame(404, $response->get_status());
    }

    // -- updateStatus --

    public function testUpdateStatusSuccess(): void
    {
        $this->listingService->method('updateStatus')->willReturn([
            'success' => true,
        ]);

        $request = new WP_REST_Request('PUT', '/bmn/v1/exclusive/1/status');
        $request->set_param('id', 1);
        $request->set_param('status', 'active');

        $response = $this->controller->updateStatus($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testUpdateStatusError(): void
    {
        $this->listingService->method('updateStatus')->willReturn([
            'success' => false,
            'errors' => ['status' => "Cannot transition from 'draft' to 'closed'."],
        ]);

        $request = new WP_REST_Request('PUT', '/bmn/v1/exclusive/1/status');
        $request->set_param('id', 1);
        $request->set_param('status', 'closed');

        $response = $this->controller->updateStatus($request);

        $this->assertSame(422, $response->get_status());
    }

    // -- getOptions --

    public function testGetOptionsSuccess(): void
    {
        $this->validator->method('getOptions')->willReturn([
            'property_types' => ['Residential', 'Commercial'],
            'statuses' => ['draft', 'active'],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive/options');

        $response = $this->controller->getOptions($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('property_types', $data['data']);
    }

    public function testGetOptionsUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive/options');
        $response = $this->controller->getOptions($request);

        $this->assertSame(401, $response->get_status());
    }
}

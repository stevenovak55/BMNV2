<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Api;

use BMN\Properties\Api\Controllers\PropertyController;
use BMN\Properties\Service\AutocompleteService;
use BMN\Properties\Service\PropertyDetailService;
use BMN\Properties\Service\PropertySearchService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class PropertyControllerTest extends TestCase
{
    private PropertySearchService $searchService;
    private PropertyDetailService $detailService;
    private AutocompleteService $autocompleteService;
    private PropertyController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];

        $this->searchService = $this->createMock(PropertySearchService::class);
        $this->detailService = $this->createMock(PropertyDetailService::class);
        $this->autocompleteService = $this->createMock(AutocompleteService::class);

        $this->controller = new PropertyController(
            $this->searchService,
            $this->detailService,
            $this->autocompleteService,
        );
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public function testRegistersSearchRoute(): void
    {
        $this->controller->registerRoutes();

        $this->assertArrayHasKey('bmn/v1/properties', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersAutocompleteRoute(): void
    {
        $this->controller->registerRoutes();

        $this->assertArrayHasKey('bmn/v1/properties/autocomplete', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersDetailRoute(): void
    {
        $this->controller->registerRoutes();

        // The detail route uses a regex pattern for listing_id.
        $found = false;
        foreach (array_keys($GLOBALS['wp_rest_routes']) as $route) {
            if (str_contains($route, 'listing_id')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Detail route with listing_id pattern should be registered');
    }

    public function testAllRoutesArePublic(): void
    {
        $this->controller->registerRoutes();

        foreach ($GLOBALS['wp_rest_routes'] as $route => $args) {
            // All property routes should use __return_true for permissions.
            $this->assertSame('__return_true', $args['permission_callback'], "Route {$route} should be public");
        }
    }

    // ------------------------------------------------------------------
    // index() - Search
    // ------------------------------------------------------------------

    public function testIndexReturnsSearchResults(): void
    {
        $this->searchService->method('search')->willReturn([
            'data' => [['listing_id' => '73464868']],
            'total' => 1,
            'page' => 1,
            'per_page' => 25,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties');
        $response = $this->controller->index($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(1, $data['meta']['total']);
    }

    public function testIndexExtractsFilterParams(): void
    {
        $this->searchService->expects($this->once())
            ->method('search')
            ->with(
                $this->callback(function (array $filters): bool {
                    return $filters['city'] === 'Boston' && $filters['beds'] === '3';
                }),
                1,
                25
            )
            ->willReturn(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 25]);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties');
        $request->set_param('city', 'Boston');
        $request->set_param('beds', '3');

        $this->controller->index($request);
    }

    public function testIndexPassesPaginationParams(): void
    {
        $this->searchService->expects($this->once())
            ->method('search')
            ->with($this->anything(), 2, 50)
            ->willReturn(['data' => [], 'total' => 0, 'page' => 2, 'per_page' => 50]);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties');
        $request->set_param('page', '2');
        $request->set_param('per_page', '50');

        $this->controller->index($request);
    }

    public function testIndexIgnoresEmptyFilterValues(): void
    {
        $this->searchService->expects($this->once())
            ->method('search')
            ->with(
                $this->callback(function (array $filters): bool {
                    return !isset($filters['city']);
                }),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 25]);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties');
        $request->set_param('city', '');

        $this->controller->index($request);
    }

    // ------------------------------------------------------------------
    // show() - Detail
    // ------------------------------------------------------------------

    public function testShowReturnsPropertyDetail(): void
    {
        $this->detailService->method('getByListingId')
            ->with('73464868')
            ->willReturn(['listing_id' => '73464868', 'city' => 'Boston']);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties/73464868');
        $request->set_param('listing_id', '73464868');

        $response = $this->controller->show($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('73464868', $data['data']['listing_id']);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->detailService->method('getByListingId')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties/nonexistent');
        $request->set_param('listing_id', 'nonexistent');

        $response = $this->controller->show($request);

        $this->assertSame(404, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function testShowReturns400WhenListingIdMissing(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/properties/');

        $response = $this->controller->show($request);

        $this->assertSame(400, $response->get_status());
    }

    // ------------------------------------------------------------------
    // autocomplete()
    // ------------------------------------------------------------------

    public function testAutocompleteReturnsSuggestions(): void
    {
        $this->autocompleteService->method('suggest')
            ->with('Bos')
            ->willReturn([
                ['value' => 'Boston', 'type' => 'city', 'count' => 500],
            ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties/autocomplete');
        $request->set_param('term', 'Bos');

        $response = $this->controller->autocomplete($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame('Boston', $data['data'][0]['value']);
    }

    public function testAutocompleteHandlesEmptyTerm(): void
    {
        $this->autocompleteService->method('suggest')->willReturn([]);

        $request = new WP_REST_Request('GET', '/bmn/v1/properties/autocomplete');

        $response = $this->controller->autocomplete($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame([], $data['data']);
    }
}

<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Api;

use BMN\Users\Api\Controllers\FavoriteController;
use BMN\Users\Service\FavoriteService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class FavoriteControllerTest extends TestCase
{
    private FavoriteService $service;
    private FavoriteController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];

        $this->service = $this->createMock(FavoriteService::class);
        $this->controller = new FavoriteController($this->service);
    }

    private function setCurrentUser(int $id): void
    {
        $user = new \WP_User($id);
        $user->user_login = 'testuser';
        $user->roles = ['subscriber'];
        $GLOBALS['current_user'] = $user;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['current_user']);
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public function testRegistersIndexRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/favorites', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersToggleRoute(): void
    {
        $this->controller->registerRoutes();

        $found = false;
        foreach (array_keys($GLOBALS['wp_rest_routes']) as $route) {
            if (str_contains($route, 'listing_id')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Toggle route with listing_id pattern should be registered');
    }

    public function testAllRoutesRequireAuth(): void
    {
        $this->controller->registerRoutes();

        foreach ($GLOBALS['wp_rest_routes'] as $route => $args) {
            $this->assertNotSame('__return_true', $args['permission_callback'], "Route {$route} should require auth");
        }
    }

    // ------------------------------------------------------------------
    // Index
    // ------------------------------------------------------------------

    public function testIndexReturnsFavorites(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('listFavorites')->willReturn([
            'listing_ids' => ['73464868', '73464869'],
            'total'       => 2,
            'page'        => 1,
            'per_page'    => 25,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/favorites');
        $response = $this->controller->index($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(2, $data['meta']['total']);
    }

    public function testIndexReturns401WhenNotAuthenticated(): void
    {
        unset($GLOBALS['current_user']);

        $request = new WP_REST_Request('GET', '/bmn/v1/favorites');
        $response = $this->controller->index($request);

        $this->assertSame(401, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Toggle
    // ------------------------------------------------------------------

    public function testToggleReturnsAddedMessage(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('toggleFavorite')->willReturn(true);

        $request = new WP_REST_Request('POST', '/bmn/v1/favorites/73464868');
        $request->set_param('listing_id', '73464868');

        $response = $this->controller->toggle($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['is_favorite']);
        $this->assertStringContainsString('Added', $data['data']['message']);
    }

    public function testToggleReturnsRemovedMessage(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('toggleFavorite')->willReturn(false);

        $request = new WP_REST_Request('POST', '/bmn/v1/favorites/73464868');
        $request->set_param('listing_id', '73464868');

        $response = $this->controller->toggle($request);

        $data = $response->get_data();
        $this->assertFalse($data['data']['is_favorite']);
        $this->assertStringContainsString('Removed', $data['data']['message']);
    }

    public function testToggleReturns400WhenListingIdEmpty(): void
    {
        $this->setCurrentUser(1);

        $request = new WP_REST_Request('POST', '/bmn/v1/favorites/');
        $request->set_param('listing_id', '');

        $response = $this->controller->toggle($request);

        $this->assertSame(400, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Remove
    // ------------------------------------------------------------------

    public function testRemoveReturnsSuccess(): void
    {
        $this->setCurrentUser(1);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/favorites/73464868');
        $request->set_param('listing_id', '73464868');

        $response = $this->controller->remove($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertFalse($data['data']['is_favorite']);
    }
}

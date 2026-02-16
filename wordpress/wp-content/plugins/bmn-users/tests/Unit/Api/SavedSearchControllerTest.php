<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Api;

use BMN\Users\Api\Controllers\SavedSearchController;
use BMN\Users\Service\SavedSearchService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class SavedSearchControllerTest extends TestCase
{
    private SavedSearchService $service;
    private SavedSearchController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];

        $this->service = $this->createMock(SavedSearchService::class);
        $this->controller = new SavedSearchController($this->service);
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
        $this->assertArrayHasKey('bmn/v1/saved-searches', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersShowRoute(): void
    {
        $this->controller->registerRoutes();

        $found = false;
        foreach (array_keys($GLOBALS['wp_rest_routes']) as $route) {
            if (str_contains($route, 'saved-searches') && str_contains($route, 'id')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Show route with id pattern should be registered');
    }

    // ------------------------------------------------------------------
    // Index
    // ------------------------------------------------------------------

    public function testIndexReturnsSearches(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('listSearches')->willReturn([
            ['id' => 1, 'name' => 'Back Bay Condos'],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/saved-searches');
        $response = $this->controller->index($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
    }

    public function testIndexReturns401WhenNotAuthenticated(): void
    {
        unset($GLOBALS['current_user']);

        $request = new WP_REST_Request('GET', '/bmn/v1/saved-searches');
        $response = $this->controller->index($request);

        $this->assertSame(401, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Store
    // ------------------------------------------------------------------

    public function testStoreReturns201OnSuccess(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('createSearch')->willReturn(42);
        $this->service->method('getSearch')->willReturn([
            'id'   => 42,
            'name' => 'My Search',
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/saved-searches');
        $request->set_param('name', 'My Search');
        $request->set_param('filters', '{"city":"Boston"}');

        $response = $this->controller->store($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(42, $data['data']['id']);
    }

    public function testStoreReturns422WhenNameMissing(): void
    {
        $this->setCurrentUser(1);

        $request = new WP_REST_Request('POST', '/bmn/v1/saved-searches');
        $request->set_param('filters', '{"city":"Boston"}');

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testStoreReturns400WhenLimitReached(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('createSearch')
            ->willThrowException(new RuntimeException('Maximum of 25 saved searches reached.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/saved-searches');
        $request->set_param('name', 'Too Many');
        $request->set_param('filters', '{}');

        $response = $this->controller->store($request);

        $this->assertSame(400, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Show
    // ------------------------------------------------------------------

    public function testShowReturnsSearch(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('getSearch')->willReturn([
            'id'   => 1,
            'name' => 'Back Bay Condos',
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/saved-searches/1');
        $request->set_param('id', '1');

        $response = $this->controller->show($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['data']['id']);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('getSearch')
            ->willThrowException(new RuntimeException('Saved search not found.'));

        $request = new WP_REST_Request('GET', '/bmn/v1/saved-searches/999');
        $request->set_param('id', '999');

        $response = $this->controller->show($request);

        $this->assertSame(404, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function testUpdateReturnsUpdatedSearch(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('updateSearch')->willReturn(true);
        $this->service->method('getSearch')->willReturn([
            'id'   => 1,
            'name' => 'Updated Name',
        ]);

        $request = new WP_REST_Request('PUT', '/bmn/v1/saved-searches/1');
        $request->set_param('id', '1');
        $request->set_param('name', 'Updated Name');

        $response = $this->controller->update($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    // ------------------------------------------------------------------
    // Destroy
    // ------------------------------------------------------------------

    public function testDestroyReturnsSuccess(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('deleteSearch')->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/saved-searches/1');
        $request->set_param('id', '1');

        $response = $this->controller->destroy($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testDestroyReturns404WhenNotFound(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('deleteSearch')
            ->willThrowException(new RuntimeException('Saved search not found.'));

        $request = new WP_REST_Request('DELETE', '/bmn/v1/saved-searches/999');
        $request->set_param('id', '999');

        $response = $this->controller->destroy($request);

        $this->assertSame(404, $response->get_status());
    }
}

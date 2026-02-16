<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Api;

use BMN\Users\Api\Controllers\UserController;
use BMN\Users\Service\UserProfileService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class UserControllerTest extends TestCase
{
    private UserProfileService $service;
    private UserController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];

        $this->service = $this->createMock(UserProfileService::class);
        $this->controller = new UserController($this->service);
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

    public function testRegistersShowRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/users/me', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersPasswordRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/users/me/password', $GLOBALS['wp_rest_routes']);
    }

    // ------------------------------------------------------------------
    // Show
    // ------------------------------------------------------------------

    public function testShowReturnsProfile(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('getProfile')->willReturn([
            'id'    => 1,
            'email' => 'test@example.com',
            'name'  => 'Test User',
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/users/me');
        $response = $this->controller->show($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['data']['id']);
    }

    public function testShowReturns401WhenNotAuthenticated(): void
    {
        unset($GLOBALS['current_user']);

        $request = new WP_REST_Request('GET', '/bmn/v1/users/me');
        $response = $this->controller->show($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testShowReturns404WhenProfileNull(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('getProfile')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/users/me');
        $response = $this->controller->show($request);

        $this->assertSame(404, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function testUpdateReturnsUpdatedProfile(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('updateProfile')->willReturn([
            'id'         => 1,
            'first_name' => 'Updated',
        ]);

        $request = new WP_REST_Request('PUT', '/bmn/v1/users/me');
        $request->set_param('first_name', 'Updated');

        $response = $this->controller->update($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('Updated', $data['data']['first_name']);
    }

    public function testUpdateReturns422WhenNoFields(): void
    {
        $this->setCurrentUser(1);

        $request = new WP_REST_Request('PUT', '/bmn/v1/users/me');

        $response = $this->controller->update($request);

        $this->assertSame(422, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Change Password
    // ------------------------------------------------------------------

    public function testChangePasswordReturnsSuccess(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('changePassword')->willReturn(true);

        $request = new WP_REST_Request('PUT', '/bmn/v1/users/me/password');
        $request->set_param('current_password', 'OldPass1!');
        $request->set_param('new_password', 'NewPass1!');

        $response = $this->controller->changePassword($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testChangePasswordReturns422WhenMissingFields(): void
    {
        $this->setCurrentUser(1);

        $request = new WP_REST_Request('PUT', '/bmn/v1/users/me/password');
        $request->set_param('current_password', 'OldPass1!');

        $response = $this->controller->changePassword($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testChangePasswordReturns400OnWrongPassword(): void
    {
        $this->setCurrentUser(1);
        $this->service->method('changePassword')
            ->willThrowException(new RuntimeException('Current password is incorrect.'));

        $request = new WP_REST_Request('PUT', '/bmn/v1/users/me/password');
        $request->set_param('current_password', 'WrongPass');
        $request->set_param('new_password', 'NewPass1!');

        $response = $this->controller->changePassword($request);

        $this->assertSame(400, $response->get_status());
    }
}

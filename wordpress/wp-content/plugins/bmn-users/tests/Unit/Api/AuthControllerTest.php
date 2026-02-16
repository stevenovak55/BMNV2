<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Api;

use BMN\Users\Api\Controllers\AuthController;
use BMN\Users\Repository\FavoriteRepository;
use BMN\Users\Repository\SavedSearchRepository;
use BMN\Users\Service\UserAuthService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class AuthControllerTest extends TestCase
{
    private UserAuthService $authService;
    private FavoriteRepository $favoriteRepo;
    private SavedSearchRepository $savedSearchRepo;
    private AuthController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];

        $this->authService = $this->createMock(UserAuthService::class);
        $this->favoriteRepo = $this->createMock(FavoriteRepository::class);
        $this->savedSearchRepo = $this->createMock(SavedSearchRepository::class);

        $this->controller = new AuthController(
            $this->authService,
            $this->favoriteRepo,
            $this->savedSearchRepo,
        );
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public function testRegistersLoginRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/auth/login', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersRegisterRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/auth/register', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersRefreshRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/auth/refresh', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersForgotPasswordRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/auth/forgot-password', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersLogoutRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/auth/logout', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersMeRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/auth/me', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersDeleteAccountRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/auth/delete-account', $GLOBALS['wp_rest_routes']);
    }

    public function testPublicRoutesHaveNoAuth(): void
    {
        $this->controller->registerRoutes();

        $publicRoutes = ['auth/login', 'auth/register', 'auth/refresh', 'auth/forgot-password'];
        foreach ($publicRoutes as $route) {
            $this->assertSame(
                '__return_true',
                $GLOBALS['wp_rest_routes']["bmn/v1/{$route}"]['permission_callback'],
                "Route {$route} should be public"
            );
        }
    }

    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------

    public function testLoginReturnsSuccessResponse(): void
    {
        $this->authService->method('login')->willReturn([
            'user'          => ['id' => 1, 'email' => 'test@example.com'],
            'access_token'  => 'tok',
            'refresh_token' => 'ref',
            'expires_in'    => 2592000,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/auth/login');
        $request->set_param('email', 'test@example.com');
        $request->set_param('password', 'Test1234!');

        $response = $this->controller->login($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame('tok', $data['data']['access_token']);
    }

    public function testLoginReturns422WhenEmailMissing(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/auth/login');
        $request->set_param('password', 'Test1234!');

        $response = $this->controller->login($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testLoginReturns401OnInvalidCredentials(): void
    {
        $this->authService->method('login')
            ->willThrowException(new RuntimeException('Invalid email or password.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/auth/login');
        $request->set_param('email', 'bad@example.com');
        $request->set_param('password', 'wrong');

        $response = $this->controller->login($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testLoginReturns429WhenRateLimited(): void
    {
        $this->authService->method('login')
            ->willThrowException(new RuntimeException('Too many login attempts.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/auth/login');
        $request->set_param('email', 'test@example.com');
        $request->set_param('password', 'Test1234!');

        $response = $this->controller->login($request);

        $this->assertSame(429, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Register
    // ------------------------------------------------------------------

    public function testRegisterReturns201OnSuccess(): void
    {
        $this->authService->method('register')->willReturn([
            'user'          => ['id' => 1, 'email' => 'new@example.com'],
            'access_token'  => 'tok',
            'refresh_token' => 'ref',
            'expires_in'    => 2592000,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/auth/register');
        $request->set_param('email', 'new@example.com');
        $request->set_param('password', 'Pass1234!');
        $request->set_param('first_name', 'New');
        $request->set_param('last_name', 'User');

        $response = $this->controller->register($request);

        $this->assertSame(201, $response->get_status());
    }

    public function testRegisterReturns409OnDuplicate(): void
    {
        $this->authService->method('register')
            ->willThrowException(new RuntimeException('An account with this email already exists.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/auth/register');
        $request->set_param('email', 'dup@example.com');
        $request->set_param('password', 'Pass1234!');
        $request->set_param('first_name', 'Dup');
        $request->set_param('last_name', 'User');

        $response = $this->controller->register($request);

        $this->assertSame(409, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Forgot Password
    // ------------------------------------------------------------------

    public function testForgotPasswordAlwaysReturnsSuccess(): void
    {
        $this->authService->method('forgotPassword')->willReturn(true);

        $request = new WP_REST_Request('POST', '/bmn/v1/auth/forgot-password');
        $request->set_param('email', 'anyone@example.com');

        $response = $this->controller->forgotPassword($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    // ------------------------------------------------------------------
    // Logout
    // ------------------------------------------------------------------

    public function testLogoutReturns401WhenNotAuthenticated(): void
    {
        // Ensure no current user.
        unset($GLOBALS['current_user']);

        $request = new WP_REST_Request('POST', '/bmn/v1/auth/logout');

        $response = $this->controller->logout($request);

        $this->assertSame(401, $response->get_status());
    }
}

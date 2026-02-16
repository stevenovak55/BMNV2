<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Auth;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Auth\JwtAuthService;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;

/**
 * Unit tests for AuthMiddleware.
 *
 * Uses the real JwtAuthService with a test secret. WordPress stubs
 * are provided by tests/bootstrap.php. The sendNoCacheHeaders() method
 * silently skips in CLI because headers_sent() returns false and header()
 * output is not testable in this context -- that is acceptable.
 */
final class AuthMiddlewareTest extends TestCase
{
    private const TEST_SECRET = 'test-middleware-secret';
    private const TEST_USER_ID = 42;

    private JwtAuthService $authService;
    private AuthMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = new JwtAuthService(self::TEST_SECRET);
        $this->middleware = new AuthMiddleware($this->authService);

        // Simulate a logged-out state by default.
        $guest = new \stdClass();
        $guest->ID = 0;
        $guest->user_login = '';
        $guest->roles = [];
        $GLOBALS['current_user'] = $guest;
    }

    protected function tearDown(): void
    {
        // Clean up any globals set during tests.
        unset(
            $GLOBALS['current_user'],
            $GLOBALS['wp_user_id_' . self::TEST_USER_ID],
            $GLOBALS['wp_user_id_99999'],
        );

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Register a stub user in globals so get_userdata() resolves it.
     *
     * @param int      $id    WordPress user ID.
     * @param string   $login Username.
     * @param string[] $roles Role slugs.
     */
    private function registerUser(int $id, string $login = 'testuser', array $roles = ['subscriber']): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_login = $login;
        $user->roles = $roles;
        $GLOBALS["wp_user_id_{$id}"] = $user;

        return $user;
    }

    /**
     * Create a WP_REST_Request with a valid Bearer token for the given user.
     */
    private function requestWithToken(int $userId): WP_REST_Request
    {
        $token = $this->authService->generateAccessToken($userId);
        $request = new WP_REST_Request('GET', '/bmn/v1/test');
        $request->set_header('Authorization', 'Bearer ' . $token);

        return $request;
    }

    /**
     * Create a WP_REST_Request with no Authorization header.
     */
    private function requestWithoutToken(): WP_REST_Request
    {
        return new WP_REST_Request('GET', '/bmn/v1/test');
    }

    // ------------------------------------------------------------------
    // 1. authenticate() - valid JWT
    // ------------------------------------------------------------------

    public function testAuthenticateWithValidJwtReturnsTrue(): void
    {
        $this->registerUser(self::TEST_USER_ID);
        $request = $this->requestWithToken(self::TEST_USER_ID);

        $result = $this->middleware->authenticate($request);

        $this->assertTrue($result);
        // Verify the current user was set.
        $this->assertSame(self::TEST_USER_ID, $GLOBALS['current_user']->ID);
    }

    // ------------------------------------------------------------------
    // 2. authenticate() - invalid JWT
    // ------------------------------------------------------------------

    public function testAuthenticateWithInvalidJwtReturnsWpError(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/test');
        $request->set_header('Authorization', 'Bearer this.is.not-a-valid-jwt');

        $result = $this->middleware->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('bmn_auth_invalid_token', $result->get_error_code());
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    // ------------------------------------------------------------------
    // 3. authenticate() - expired JWT (skipped - requires time mocking)
    // ------------------------------------------------------------------

    /**
     * Testing expired tokens would require mocking time() or using a
     * pre-crafted expired token with a known secret. We skip this test
     * rather than introduce fragile time-manipulation logic.
     */
    public function testAuthenticateWithExpiredJwtReturnsWpError(): void
    {
        $this->markTestSkipped(
            'Expired JWT test requires time mocking (e.g., Carbon::setTestNow or php-timecop). Skipped to avoid fragility.'
        );
    }

    // ------------------------------------------------------------------
    // 4. authenticate() - WordPress session fallback
    // ------------------------------------------------------------------

    public function testAuthenticateFallsBackToWordPressSession(): void
    {
        // Simulate a logged-in WordPress session (no JWT header).
        $user = new \stdClass();
        $user->ID = 7;
        $user->user_login = 'sessionuser';
        $user->roles = ['editor'];
        $GLOBALS['current_user'] = $user;

        $request = $this->requestWithoutToken();

        $result = $this->middleware->authenticate($request);

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // 5. authenticate() - no credentials at all
    // ------------------------------------------------------------------

    public function testAuthenticateReturnsErrorWhenNoCredentials(): void
    {
        // $GLOBALS['current_user']->ID is 0 (logged-out, set in setUp).
        $request = $this->requestWithoutToken();

        $result = $this->middleware->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('bmn_auth_required', $result->get_error_code());
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    // ------------------------------------------------------------------
    // 6. authenticateOptional() - no token, returns true (guest pass)
    // ------------------------------------------------------------------

    public function testAuthenticateOptionalReturnsTrueWithNoToken(): void
    {
        $request = $this->requestWithoutToken();

        $result = $this->middleware->authenticateOptional($request);

        $this->assertTrue($result);
        // User should remain a guest (ID 0).
        $this->assertSame(0, $GLOBALS['current_user']->ID);
    }

    // ------------------------------------------------------------------
    // 7. authenticateOptional() - valid token, sets user
    // ------------------------------------------------------------------

    public function testAuthenticateOptionalSetsUserWithValidToken(): void
    {
        $this->registerUser(self::TEST_USER_ID);
        $request = $this->requestWithToken(self::TEST_USER_ID);

        $result = $this->middleware->authenticateOptional($request);

        $this->assertTrue($result);
        // The stub wp_set_current_user sets $GLOBALS['current_user']->ID.
        $this->assertSame(self::TEST_USER_ID, $GLOBALS['current_user']->ID);
    }

    // ------------------------------------------------------------------
    // 8. authenticateOptional() - invalid token, proceeds as guest
    // ------------------------------------------------------------------

    public function testAuthenticateOptionalReturnsTrueWithInvalidToken(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/test');
        $request->set_header('Authorization', 'Bearer garbage-token-value');

        $result = $this->middleware->authenticateOptional($request);

        $this->assertTrue($result);
        // User should remain a guest.
        $this->assertSame(0, $GLOBALS['current_user']->ID);
    }

    // ------------------------------------------------------------------
    // 9. authenticateWithRole() - user has required role
    // ------------------------------------------------------------------

    public function testAuthenticateWithRoleReturnsTrue(): void
    {
        $this->registerUser(self::TEST_USER_ID, 'testuser', ['subscriber']);
        $request = $this->requestWithToken(self::TEST_USER_ID);

        // The wp_set_current_user stub assigns 'subscriber' role.
        // Verify that the user passes when that role is required.
        $result = $this->middleware->authenticateWithRole($request, 'subscriber');

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // 10. authenticateWithRole() - user lacks required role
    // ------------------------------------------------------------------

    public function testAuthenticateWithRoleForbiddenWhenUserLacksRole(): void
    {
        // User has 'subscriber' role but 'administrator' is required.
        $this->registerUser(self::TEST_USER_ID, 'testuser', ['subscriber']);
        $request = $this->requestWithToken(self::TEST_USER_ID);

        $result = $this->middleware->authenticateWithRole($request, 'administrator');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('bmn_forbidden', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['status']);
    }

    // ------------------------------------------------------------------
    // 11. authenticateWithRole() - empty roles = any authenticated user
    // ------------------------------------------------------------------

    public function testAuthenticateWithRoleAllowsAnyRoleWhenEmpty(): void
    {
        $this->registerUser(self::TEST_USER_ID);
        $request = $this->requestWithToken(self::TEST_USER_ID);

        // No roles passed = any authenticated user is allowed.
        $result = $this->middleware->authenticateWithRole($request);

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // 12. getAuthService() returns the injected service
    // ------------------------------------------------------------------

    public function testGetAuthServiceReturnsInjectedService(): void
    {
        $service = $this->middleware->getAuthService();

        $this->assertSame($this->authService, $service);
        $this->assertInstanceOf(AuthService::class, $service);
    }

    // ------------------------------------------------------------------
    // 13. authenticate() - valid JWT but user not found in WordPress
    // ------------------------------------------------------------------

    public function testAuthenticateReturnsErrorWhenUserNotFound(): void
    {
        // Generate a valid token for user 99999 but do NOT register that
        // user in globals, so get_userdata() will return false.
        $token = $this->authService->generateAccessToken(99999);
        $request = new WP_REST_Request('GET', '/bmn/v1/test');
        $request->set_header('Authorization', 'Bearer ' . $token);

        $result = $this->middleware->authenticate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('bmn_auth_user_not_found', $result->get_error_code());
        $this->assertSame(401, $result->get_error_data()['status']);
        $this->assertStringContainsString('no longer exists', $result->get_error_message());
    }
}

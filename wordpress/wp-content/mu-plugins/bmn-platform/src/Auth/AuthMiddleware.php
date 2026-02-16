<?php

declare(strict_types=1);

namespace BMN\Platform\Auth;

use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;

/**
 * REST API authentication middleware.
 *
 * Validates JWT tokens on incoming requests and falls back to the
 * native WordPress session cookie when no token is present. Successful
 * JWT authentication sets the current WP user for the request lifecycle.
 *
 * CDN bypass: Adds Cache-Control and Vary headers so that CDN-cached
 * responses never leak user-specific data.
 */
final class AuthMiddleware
{
    private readonly AuthService $auth;

    /** @var array<string, string> Roles allowed for a given request (empty = any authenticated). */
    private array $requiredRoles = [];

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Authenticate the incoming REST request.
     *
     * Priority: JWT Bearer token > WordPress session cookie.
     *
     * @return bool|WP_Error True if authenticated, WP_Error on failure.
     */
    public function authenticate(WP_REST_Request $request): bool|WP_Error
    {
        $this->sendNoCacheHeaders();

        // --- Attempt 1: JWT Bearer token ---
        $token = $this->extractBearerToken($request);

        if ($token !== null) {
            return $this->authenticateWithJwt($token);
        }

        // --- Attempt 2: WordPress session cookie ---
        $userId = get_current_user_id();

        if ($userId > 0) {
            return true;
        }

        return new WP_Error(
            'bmn_auth_required',
            'Authentication required. Provide a valid JWT token or WordPress session.',
            ['status' => 401]
        );
    }

    /**
     * Optional authentication — allows anonymous access but authenticates
     * if credentials are present (for endpoints that show extra data to
     * logged-in users, e.g. favorite status on property listings).
     *
     * Always returns true so the request proceeds, but may set the
     * current user if a valid token is found.
     */
    public function authenticateOptional(WP_REST_Request $request): bool
    {
        $token = $this->extractBearerToken($request);

        if ($token !== null) {
            try {
                $payload = $this->auth->validateToken($token, 'access');
                wp_set_current_user($payload['sub']);
            } catch (InvalidArgumentException) {
                // Invalid token on an optional-auth route — proceed as guest.
            }
        }

        return true;
    }

    /**
     * Authenticate and enforce role-based access.
     *
     * @param WP_REST_Request $request The incoming request.
     * @param string          ...$roles One or more role slugs that are permitted.
     *
     * @return bool|WP_Error True if the user has one of the required roles.
     */
    public function authenticateWithRole(WP_REST_Request $request, string ...$roles): bool|WP_Error
    {
        $result = $this->authenticate($request);

        if ($result !== true) {
            return $result;
        }

        if ($roles === []) {
            return true;
        }

        $user = wp_get_current_user();
        $userRoles = (array) ($user->roles ?? []);

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return new WP_Error(
            'bmn_forbidden',
            'You do not have permission to access this resource.',
            ['status' => 403]
        );
    }

    /**
     * Return the AuthService instance (for controllers that need direct access).
     */
    public function getAuthService(): AuthService
    {
        return $this->auth;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Extract a Bearer token from the Authorization header.
     */
    private function extractBearerToken(WP_REST_Request $request): ?string
    {
        $header = $request->get_header('authorization');

        if ($header === null || $header === '') {
            return null;
        }

        // Support "Bearer <token>" format.
        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
            return $token !== '' ? $token : null;
        }

        return null;
    }

    /**
     * Validate a JWT and set the current WordPress user.
     *
     * Fires the `bmn_is_token_revoked` filter after successful JWT
     * validation so that plugins (e.g. bmn-users) can reject revoked tokens.
     *
     * @return bool|WP_Error True on success.
     */
    private function authenticateWithJwt(string $token): bool|WP_Error
    {
        try {
            $payload = $this->auth->validateToken($token, 'access');
        } catch (InvalidArgumentException $e) {
            return new WP_Error(
                'bmn_auth_invalid_token',
                $e->getMessage(),
                ['status' => 401]
            );
        }

        // Allow plugins to reject revoked tokens.
        $revoked = apply_filters('bmn_is_token_revoked', false, $token, $payload);

        if ($revoked) {
            return new WP_Error(
                'bmn_auth_token_revoked',
                'Token has been revoked.',
                ['status' => 401]
            );
        }

        $userId = $payload['sub'];

        // Verify the user still exists in WordPress.
        $user = get_userdata($userId);

        if ($user === false) {
            return new WP_Error(
                'bmn_auth_user_not_found',
                'The user associated with this token no longer exists.',
                ['status' => 401]
            );
        }

        wp_set_current_user($userId);

        return true;
    }

    /**
     * Send headers that prevent CDNs and proxies from caching
     * authenticated responses.
     */
    private function sendNoCacheHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Vary: Authorization');
    }
}

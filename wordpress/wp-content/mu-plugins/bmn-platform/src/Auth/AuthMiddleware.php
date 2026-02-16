<?php

declare(strict_types=1);

namespace BMN\Platform\Auth;

use WP_Error;
use WP_REST_Request;

/**
 * REST API authentication middleware.
 *
 * Validates JWT tokens on incoming requests and sets the current
 * WordPress user when authentication succeeds.
 *
 * @todo Implement in Phase 2.
 */
class AuthMiddleware
{
    /**
     * Authenticate the incoming REST request.
     *
     * @return bool|WP_Error True if authenticated, WP_Error on failure.
     */
    public function authenticate(WP_REST_Request $request): bool|WP_Error
    {
        // Stub -- always denies until implemented.
        return new WP_Error(
            'bmn_auth_not_implemented',
            'Authentication middleware is not yet implemented.',
            ['status' => 501]
        );
    }
}

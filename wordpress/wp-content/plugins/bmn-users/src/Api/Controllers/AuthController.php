<?php

declare(strict_types=1);

namespace BMN\Users\Api\Controllers;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use BMN\Users\Repository\FavoriteRepository;
use BMN\Users\Repository\SavedSearchRepository;
use BMN\Users\Service\UserAuthService;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for authentication endpoints.
 */
final class AuthController extends RestController
{
    protected string $resource = 'auth';

    private readonly UserAuthService $authService;
    private readonly FavoriteRepository $favoriteRepo;
    private readonly SavedSearchRepository $savedSearchRepo;

    public function __construct(
        UserAuthService $authService,
        FavoriteRepository $favoriteRepo,
        SavedSearchRepository $savedSearchRepo,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->authService = $authService;
        $this->favoriteRepo = $favoriteRepo;
        $this->savedSearchRepo = $savedSearchRepo;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '/login',
                'method'   => 'POST',
                'callback' => 'login',
                'auth'     => false,
            ],
            [
                'path'     => '/register',
                'method'   => 'POST',
                'callback' => 'register',
                'auth'     => false,
            ],
            [
                'path'     => '/refresh',
                'method'   => 'POST',
                'callback' => 'refresh',
                'auth'     => false,
            ],
            [
                'path'     => '/forgot-password',
                'method'   => 'POST',
                'callback' => 'forgotPassword',
                'auth'     => false,
            ],
            [
                'path'     => '/logout',
                'method'   => 'POST',
                'callback' => 'logout',
                'auth'     => true,
            ],
            [
                'path'     => '/me',
                'method'   => 'GET',
                'callback' => 'me',
                'auth'     => true,
            ],
            [
                'path'     => '/delete-account',
                'method'   => 'DELETE',
                'callback' => 'deleteAccount',
                'auth'     => true,
            ],
        ];
    }

    public function login(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'email'    => ['type' => 'email', 'required' => true],
            'password' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $result = $this->authService->login(
                (string) $request->get_param('email'),
                (string) $request->get_param('password'),
            );

            return ApiResponse::success($result);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'Too many') ? 429 : 401;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    public function register(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'email'      => ['type' => 'email', 'required' => true],
            'password'   => ['type' => 'string', 'required' => true],
            'first_name' => ['type' => 'string', 'required' => true],
            'last_name'  => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $result = $this->authService->register(
                (string) $request->get_param('email'),
                (string) $request->get_param('password'),
                (string) $request->get_param('first_name'),
                (string) $request->get_param('last_name'),
                (string) ($request->get_param('phone') ?? ''),
            );

            return ApiResponse::success($result, [], 201);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'already exists') ? 409 : 400;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    public function refresh(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'refresh_token' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $result = $this->authService->refreshToken(
                (string) $request->get_param('refresh_token'),
            );

            return ApiResponse::success($result);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 401);
        }
    }

    public function forgotPassword(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'email' => ['type' => 'email', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $this->authService->forgotPassword(
            (string) $request->get_param('email'),
        );

        // Always return success to prevent email enumeration.
        return ApiResponse::success([
            'message' => 'If an account exists with that email, a password reset link has been sent.',
        ]);
    }

    public function logout(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $token = $this->extractToken($request);

        if ($token !== null) {
            $this->authService->logout($token, (int) $user->ID);
        }

        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }

    public function me(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $profile = \BMN\Users\Service\UserProfileFormatter::format($user);

        return ApiResponse::success($profile);
    }

    public function deleteAccount(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        try {
            $this->authService->deleteAccount(
                (int) $user->ID,
                $this->favoriteRepo,
                $this->savedSearchRepo,
            );

            return ApiResponse::success(['message' => 'Account and all associated data have been permanently deleted.']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Extract the raw Bearer token from the request.
     */
    private function extractToken(WP_REST_Request $request): ?string
    {
        $header = $request->get_header('authorization');

        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}

<?php

declare(strict_types=1);

namespace BMN\Users\Api\Controllers;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use BMN\Users\Service\UserProfileService;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for user profile endpoints.
 */
final class UserController extends RestController
{
    protected string $resource = 'users';

    private readonly UserProfileService $profileService;

    public function __construct(UserProfileService $profileService, ?AuthMiddleware $authMiddleware = null)
    {
        parent::__construct($authMiddleware);
        $this->profileService = $profileService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '/me',
                'method'   => 'GET',
                'callback' => 'show',
                'auth'     => true,
            ],
            [
                'path'     => '/me',
                'method'   => 'PUT',
                'callback' => 'update',
                'auth'     => true,
            ],
            [
                'path'     => '/me/password',
                'method'   => 'PUT',
                'callback' => 'changePassword',
                'auth'     => true,
            ],
        ];
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $profile = $this->profileService->getProfile((int) $user->ID);

        if ($profile === null) {
            return ApiResponse::error('User not found.', 404);
        }

        return ApiResponse::success($profile);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $data = [];

        foreach (['first_name', 'last_name', 'phone', 'email'] as $field) {
            $value = $request->get_param($field);

            if ($value !== null) {
                $data[$field] = (string) $value;
            }
        }

        if ($data === []) {
            return ApiResponse::error('No fields to update.', 422);
        }

        try {
            $profile = $this->profileService->updateProfile((int) $user->ID, $data);

            return ApiResponse::success($profile);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function changePassword(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'current_password' => ['type' => 'string', 'required' => true],
            'new_password'     => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $this->profileService->changePassword(
                (int) $user->ID,
                (string) $request->get_param('current_password'),
                (string) $request->get_param('new_password'),
            );

            return ApiResponse::success(['message' => 'Password changed successfully.']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}

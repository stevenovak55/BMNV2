<?php

declare(strict_types=1);

namespace BMN\Exclusive\Api\Controllers;

use BMN\Exclusive\Service\ListingService;
use BMN\Exclusive\Service\ValidationService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

final class ListingController extends RestController
{
    protected string $resource = 'exclusive';

    public function __construct(
        private readonly ListingService $listingService,
        private readonly ValidationService $validator,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
    }

    protected function getRoutes(): array
    {
        return [
            ['path' => '', 'method' => 'GET', 'callback' => 'listListings', 'auth' => true],
            ['path' => '', 'method' => 'POST', 'callback' => 'createListing', 'auth' => true],
            ['path' => '/(?P<id>\d+)', 'method' => 'GET', 'callback' => 'getListing', 'auth' => true],
            ['path' => '/(?P<id>\d+)', 'method' => 'PUT', 'callback' => 'updateListing', 'auth' => true],
            ['path' => '/(?P<id>\d+)', 'method' => 'DELETE', 'callback' => 'deleteListing', 'auth' => true],
            ['path' => '/(?P<id>\d+)/status', 'method' => 'PUT', 'callback' => 'updateStatus', 'auth' => true],
            ['path' => '/options', 'method' => 'GET', 'callback' => 'getOptions', 'auth' => true],
        ];
    }

    public function listListings(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $page = (int) ($request->get_param('page') ?: 1);
        $perPage = (int) ($request->get_param('per_page') ?: 20);
        $status = $request->get_param('status');

        $result = $this->listingService->getAgentListings($user->ID, $page, $perPage, $status);

        return ApiResponse::paginated($result['listings'], $result['total'], $page, $perPage);
    }

    public function createListing(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $data = $request->get_json_params();

        $result = $this->listingService->createListing($user->ID, $data);

        if (isset($result['errors'])) {
            $firstError = reset($result['errors']);
            return ApiResponse::error($firstError, 422, $result['errors']);
        }

        return ApiResponse::success($result);
    }

    public function getListing(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $listing = $this->listingService->getListing($id, $user->ID);

        if ($listing === null) {
            return ApiResponse::error('Listing not found.', 404);
        }

        return ApiResponse::success($listing);
    }

    public function updateListing(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $data = $request->get_json_params();

        $result = $this->listingService->updateListing($id, $user->ID, $data);

        if (isset($result['errors'])) {
            $firstError = reset($result['errors']);
            return ApiResponse::error($firstError, 422, $result['errors']);
        }

        return ApiResponse::success($result);
    }

    public function deleteListing(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $deleted = $this->listingService->deleteListing($id, $user->ID);

        if (!$deleted) {
            return ApiResponse::error('Listing not found.', 404);
        }

        return ApiResponse::success(['deleted' => true]);
    }

    public function updateStatus(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $status = (string) $request->get_param('status');

        $result = $this->listingService->updateStatus($id, $user->ID, $status);

        if (isset($result['errors'])) {
            $firstError = reset($result['errors']);
            return ApiResponse::error($firstError, 422, $result['errors']);
        }

        return ApiResponse::success($result);
    }

    public function getOptions(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $options = $this->validator->getOptions();

        return ApiResponse::success($options);
    }
}

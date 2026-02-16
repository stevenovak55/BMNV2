<?php

declare(strict_types=1);

namespace BMN\Users\Api\Controllers;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use BMN\Users\Service\SavedSearchService;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for saved search endpoints.
 */
final class SavedSearchController extends RestController
{
    protected string $resource = 'saved-searches';

    private readonly SavedSearchService $searchService;

    public function __construct(SavedSearchService $searchService, ?AuthMiddleware $authMiddleware = null)
    {
        parent::__construct($authMiddleware);
        $this->searchService = $searchService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'index',
                'auth'     => true,
            ],
            [
                'path'     => '',
                'method'   => 'POST',
                'callback' => 'store',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'GET',
                'callback' => 'show',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'PUT',
                'callback' => 'update',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'DELETE',
                'callback' => 'destroy',
                'auth'     => true,
            ],
        ];
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $searches = $this->searchService->listSearches((int) $user->ID);

        return ApiResponse::success($searches);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'name'    => ['type' => 'string', 'required' => true],
            'filters' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $filters = $request->get_param('filters');

        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        if (! is_array($filters)) {
            return ApiResponse::error('Filters must be a valid JSON object.', 422);
        }

        $polygonShapes = $request->get_param('polygon_shapes');

        if (is_string($polygonShapes)) {
            $polygonShapes = json_decode($polygonShapes, true);
        }

        try {
            $searchId = $this->searchService->createSearch(
                (int) $user->ID,
                (string) $request->get_param('name'),
                $filters,
                is_array($polygonShapes) ? $polygonShapes : null,
            );

            $search = $this->searchService->getSearch((int) $user->ID, $searchId);

            return ApiResponse::success($search, [], 201);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $searchId = (int) $request->get_param('id');

        try {
            $search = $this->searchService->getSearch((int) $user->ID, $searchId);

            return ApiResponse::success($search);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $searchId = (int) $request->get_param('id');

        $data = [];

        $name = $request->get_param('name');
        if ($name !== null) {
            $data['name'] = (string) $name;
        }

        $filters = $request->get_param('filters');
        if ($filters !== null) {
            if (is_string($filters)) {
                $filters = json_decode($filters, true);
            }
            if (is_array($filters)) {
                $data['filters'] = $filters;
            }
        }

        $polygonShapes = $request->get_param('polygon_shapes');
        if ($polygonShapes !== null) {
            if (is_string($polygonShapes)) {
                $polygonShapes = json_decode($polygonShapes, true);
            }
            $data['polygon_shapes'] = is_array($polygonShapes) ? $polygonShapes : null;
        }

        $isActive = $request->get_param('is_active');
        if ($isActive !== null) {
            $data['is_active'] = (bool) $isActive;
        }

        try {
            $this->searchService->updateSearch((int) $user->ID, $searchId, $data);
            $search = $this->searchService->getSearch((int) $user->ID, $searchId);

            return ApiResponse::success($search);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $searchId = (int) $request->get_param('id');

        try {
            $this->searchService->deleteSearch((int) $user->ID, $searchId);

            return ApiResponse::success(['message' => 'Saved search deleted.']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }
}

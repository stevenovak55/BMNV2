<?php

declare(strict_types=1);

namespace BMN\Users\Api\Controllers;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use BMN\Users\Service\FavoriteService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for favorite endpoints.
 */
final class FavoriteController extends RestController
{
    protected string $resource = 'favorites';

    private readonly FavoriteService $favoriteService;

    public function __construct(FavoriteService $favoriteService, ?AuthMiddleware $authMiddleware = null)
    {
        parent::__construct($authMiddleware);
        $this->favoriteService = $favoriteService;
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
                'path'     => '/(?P<listing_id>[a-zA-Z0-9]+)',
                'method'   => 'POST',
                'callback' => 'toggle',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<listing_id>[a-zA-Z0-9]+)',
                'method'   => 'DELETE',
                'callback' => 'remove',
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

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 25)));

        $result = $this->favoriteService->listFavorites((int) $user->ID, $page, $perPage);

        return ApiResponse::paginated(
            $result['listing_ids'],
            $result['total'],
            $result['page'],
            $result['per_page'],
        );
    }

    public function toggle(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $listingId = (string) $request->get_param('listing_id');

        if ($listingId === '') {
            return ApiResponse::error('Listing ID is required.', 400);
        }

        $added = $this->favoriteService->toggleFavorite((int) $user->ID, $listingId);

        return ApiResponse::success([
            'message'     => $added ? 'Added to favorites.' : 'Removed from favorites.',
            'is_favorite' => $added,
        ]);
    }

    public function remove(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $listingId = (string) $request->get_param('listing_id');

        if ($listingId === '') {
            return ApiResponse::error('Listing ID is required.', 400);
        }

        $this->favoriteService->removeFavorite((int) $user->ID, $listingId);

        return ApiResponse::success([
            'message'     => 'Removed from favorites.',
            'is_favorite' => false,
        ]);
    }
}

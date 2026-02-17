<?php

declare(strict_types=1);

namespace BMN\Exclusive\Api\Controllers;

use BMN\Exclusive\Service\PhotoService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

final class PhotoController extends RestController
{
    protected string $resource = 'exclusive';

    public function __construct(
        private readonly PhotoService $photoService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
    }

    protected function getRoutes(): array
    {
        return [
            ['path' => '/(?P<id>\d+)/photos', 'method' => 'GET', 'callback' => 'getPhotos', 'auth' => true],
            ['path' => '/(?P<id>\d+)/photos', 'method' => 'POST', 'callback' => 'addPhoto', 'auth' => true],
            ['path' => '/(?P<id>\d+)/photos/(?P<photo_id>\d+)', 'method' => 'DELETE', 'callback' => 'deletePhoto', 'auth' => true],
            ['path' => '/(?P<id>\d+)/photos/order', 'method' => 'PUT', 'callback' => 'reorderPhotos', 'auth' => true],
        ];
    }

    public function getPhotos(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $photos = $this->photoService->getPhotos($id, $user->ID);

        if ($photos === null) {
            return ApiResponse::error('Listing not found.', 404);
        }

        return ApiResponse::success($photos);
    }

    public function addPhoto(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $mediaUrl = (string) $request->get_param('media_url');

        $result = $this->photoService->addPhoto($id, $user->ID, $mediaUrl);

        if (isset($result['errors'])) {
            $firstError = reset($result['errors']);
            return ApiResponse::error($firstError, 422, $result['errors']);
        }

        return ApiResponse::success($result);
    }

    public function deletePhoto(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $photoId = (int) $request->get_param('photo_id');

        $deleted = $this->photoService->deletePhoto($id, $user->ID, $photoId);

        if (!$deleted) {
            return ApiResponse::error('Photo not found.', 404);
        }

        return ApiResponse::success(['deleted' => true]);
    }

    public function reorderPhotos(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $id = (int) $request->get_param('id');
        $photos = $request->get_param('photos') ?? [];

        $reordered = $this->photoService->reorderPhotos($id, $user->ID, $photos);

        if (!$reordered) {
            return ApiResponse::error('Failed to reorder photos.', 422);
        }

        return ApiResponse::success(['reordered' => true]);
    }
}

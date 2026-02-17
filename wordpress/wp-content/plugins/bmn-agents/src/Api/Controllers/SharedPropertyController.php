<?php

declare(strict_types=1);

namespace BMN\Agents\Api\Controllers;

use BMN\Agents\Service\SharedPropertyService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

final class SharedPropertyController extends RestController
{
    protected string $resource = '';

    private readonly SharedPropertyService $sharedService;

    public function __construct(
        SharedPropertyService $sharedService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->sharedService = $sharedService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => 'agent/share-properties',
                'method'   => 'POST',
                'callback' => 'shareProperties',
                'auth'     => true,
            ],
            [
                'path'     => 'shared-properties',
                'method'   => 'GET',
                'callback' => 'getSharedProperties',
                'auth'     => true,
            ],
            [
                'path'     => 'shared-properties/(?P<id>\d+)/respond',
                'method'   => 'PUT',
                'callback' => 'respondToShare',
                'auth'     => true,
            ],
            [
                'path'     => 'shared-properties/(?P<id>\d+)/dismiss',
                'method'   => 'PUT',
                'callback' => 'dismissShare',
                'auth'     => true,
            ],
        ];
    }

    /**
     * POST /agent/share-properties — Share listing(s) with client(s).
     */
    public function shareProperties(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $clientIds = $request->get_param('client_ids');
        $listingIds = $request->get_param('listing_ids');

        if (empty($clientIds) || !is_array($clientIds)) {
            return ApiResponse::error('client_ids is required and must be an array.', 422);
        }

        if (empty($listingIds) || !is_array($listingIds)) {
            return ApiResponse::error('listing_ids is required and must be an array.', 422);
        }

        $note = $request->get_param('note');
        $count = $this->sharedService->shareProperties(
            (int) $user->ID,
            array_map('intval', $clientIds),
            array_map('strval', $listingIds),
            $note !== null ? (string) $note : null,
        );

        return ApiResponse::success(['shared_count' => $count], [], 201);
    }

    /**
     * GET /shared-properties — Get properties shared with me.
     */
    public function getSharedProperties(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        $includeDismissed = (bool) ($request->get_param('include_dismissed') ?? false);

        $result = $this->sharedService->getSharedForClient(
            (int) $user->ID,
            $includeDismissed,
            $page,
            $perPage
        );

        $items = array_map(static fn (object $s): array => [
            'id'              => (int) $s->id,
            'agent_user_id'   => (int) $s->agent_user_id,
            'listing_id'      => $s->listing_id,
            'agent_note'      => $s->agent_note ?? null,
            'client_response' => $s->client_response,
            'client_note'     => $s->client_note ?? null,
            'is_dismissed'    => (bool) $s->is_dismissed,
            'view_count'      => (int) $s->view_count,
            'first_viewed_at' => $s->first_viewed_at ?? null,
            'shared_at'       => $s->shared_at,
        ], $result['items']);

        return ApiResponse::paginated($items, $result['total'], $page, $perPage);
    }

    /**
     * PUT /shared-properties/{id}/respond — Client responds interested/not.
     */
    public function respondToShare(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'response' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $this->sharedService->respondToShare(
                (int) $request->get_param('id'),
                (int) $user->ID,
                (string) $request->get_param('response'),
                $request->get_param('note') !== null ? (string) $request->get_param('note') : null,
            );
            return ApiResponse::success(['id' => (int) $request->get_param('id')]);
        } catch (RuntimeException $e) {
            $code = match (true) {
                str_contains($e->getMessage(), 'not found') => 404,
                str_contains($e->getMessage(), 'Not authorized') => 403,
                default => 400,
            };
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * PUT /shared-properties/{id}/dismiss — Client dismisses shared listing.
     */
    public function dismissShare(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        try {
            $this->sharedService->dismissShare(
                (int) $request->get_param('id'),
                (int) $user->ID
            );
            return ApiResponse::success(['id' => (int) $request->get_param('id'), 'dismissed' => true]);
        } catch (RuntimeException $e) {
            $code = match (true) {
                str_contains($e->getMessage(), 'not found') => 404,
                str_contains($e->getMessage(), 'Not authorized') => 403,
                default => 400,
            };
            return ApiResponse::error($e->getMessage(), $code);
        }
    }
}

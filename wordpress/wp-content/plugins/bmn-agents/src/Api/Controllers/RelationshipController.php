<?php

declare(strict_types=1);

namespace BMN\Agents\Api\Controllers;

use BMN\Agents\Service\RelationshipService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

final class RelationshipController extends RestController
{
    protected string $resource = '';

    private readonly RelationshipService $relationshipService;

    public function __construct(
        RelationshipService $relationshipService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->relationshipService = $relationshipService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => 'my-agent',
                'method'   => 'GET',
                'callback' => 'getMyAgent',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/clients',
                'method'   => 'GET',
                'callback' => 'getClients',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/clients',
                'method'   => 'POST',
                'callback' => 'createClient',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/clients/(?P<client_id>\d+)/status',
                'method'   => 'PUT',
                'callback' => 'updateClientStatus',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/clients/(?P<client_id>\d+)',
                'method'   => 'DELETE',
                'callback' => 'unassignClient',
                'auth'     => true,
            ],
        ];
    }

    /**
     * GET /my-agent — Get client's assigned agent.
     */
    public function getMyAgent(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $relationship = $this->relationshipService->getClientAgent((int) $user->ID);

        if ($relationship === null) {
            return ApiResponse::success(null);
        }

        return ApiResponse::success([
            'id'             => (int) $relationship->id,
            'agent_user_id'  => (int) $relationship->agent_user_id,
            'status'         => $relationship->status,
            'source'         => $relationship->source,
            'assigned_at'    => $relationship->assigned_at,
        ]);
    }

    /**
     * GET /agent/clients — Agent's client list (paginated).
     */
    public function getClients(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        $status = $request->get_param('status');

        $result = $this->relationshipService->getAgentClients(
            (int) $user->ID,
            $status,
            $page,
            $perPage
        );

        $items = array_map(static fn (object $r): array => [
            'id'              => (int) $r->id,
            'client_user_id'  => (int) $r->client_user_id,
            'status'          => $r->status,
            'source'          => $r->source,
            'notes'           => $r->notes ?? null,
            'assigned_at'     => $r->assigned_at,
        ], $result['items']);

        return ApiResponse::paginated($items, $result['total'], $page, $perPage);
    }

    /**
     * POST /agent/clients — Create new client + auto-assign.
     */
    public function createClient(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'email'      => ['type' => 'email', 'required' => true],
            'first_name' => ['type' => 'string', 'required' => true],
            'last_name'  => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $result = $this->relationshipService->createClient([
                'email'      => (string) $request->get_param('email'),
                'first_name' => (string) $request->get_param('first_name'),
                'last_name'  => (string) $request->get_param('last_name'),
                'notes'      => $request->get_param('notes'),
            ], (int) $user->ID);

            return ApiResponse::success($result, [], 201);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /agent/clients/{client_id}/status — Update relationship status.
     */
    public function updateClientStatus(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'status' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $clientId = (int) $request->get_param('client_id');
        $status = (string) $request->get_param('status');

        if (!in_array($status, ['active', 'inactive', 'pending'], true)) {
            return ApiResponse::error('Invalid status. Must be active, inactive, or pending.', 422);
        }

        try {
            $this->relationshipService->updateStatus((int) $user->ID, $clientId, $status);
            return ApiResponse::success(['client_id' => $clientId, 'status' => $status]);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * DELETE /agent/clients/{client_id} — Unassign client.
     */
    public function unassignClient(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $clientId = (int) $request->get_param('client_id');

        try {
            $this->relationshipService->unassignAgent((int) $user->ID, $clientId);
            return ApiResponse::success(['client_id' => $clientId, 'status' => 'inactive']);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }
}

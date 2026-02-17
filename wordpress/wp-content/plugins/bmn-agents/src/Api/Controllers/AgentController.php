<?php

declare(strict_types=1);

namespace BMN\Agents\Api\Controllers;

use BMN\Agents\Service\AgentProfileService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

final class AgentController extends RestController
{
    protected string $resource = 'agents';

    private readonly AgentProfileService $profileService;

    public function __construct(
        AgentProfileService $profileService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->profileService = $profileService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'listAgents',
                'auth'     => false,
            ],
            [
                'path'     => '/featured',
                'method'   => 'GET',
                'callback' => 'getFeaturedAgents',
                'auth'     => false,
            ],
            [
                'path'     => '/(?P<agent_mls_id>[^/]+)',
                'method'   => 'GET',
                'callback' => 'getAgent',
                'auth'     => false,
            ],
            [
                'path'     => '/(?P<agent_mls_id>[^/]+)/profile',
                'method'   => 'PUT',
                'callback' => 'updateProfile',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<agent_mls_id>[^/]+)/link-user',
                'method'   => 'POST',
                'callback' => 'linkUser',
                'auth'     => true,
            ],
        ];
    }

    /**
     * GET /agents — List active agents (paginated, with office).
     */
    public function listAgents(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        $filters = array_filter([
            'search' => $request->get_param('search'),
        ]);

        $result = $this->profileService->listAgents($filters, $page, $perPage);

        return ApiResponse::paginated($result['items'], $result['total'], $page, $perPage);
    }

    /**
     * GET /agents/featured — Featured agents for homepage.
     */
    public function getFeaturedAgents(WP_REST_Request $request): WP_REST_Response
    {
        $agents = $this->profileService->getFeaturedAgents();

        return ApiResponse::success($agents);
    }

    /**
     * GET /agents/{agent_mls_id} — Single agent with profile + office.
     */
    public function getAgent(WP_REST_Request $request): WP_REST_Response
    {
        $agentMlsId = (string) $request->get_param('agent_mls_id');
        $agent = $this->profileService->getAgent($agentMlsId);

        if ($agent === null) {
            return ApiResponse::error('Agent not found.', 404);
        }

        return ApiResponse::success($agent);
    }

    /**
     * PUT /agents/{agent_mls_id}/profile — Update extended profile (admin).
     */
    public function updateProfile(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $agentMlsId = (string) $request->get_param('agent_mls_id');

        $data = array_filter([
            'bio'           => $request->get_param('bio'),
            'photo_url'     => $request->get_param('photo_url'),
            'specialties'   => $request->get_param('specialties'),
            'is_featured'   => $request->get_param('is_featured'),
            'is_active'     => $request->get_param('is_active'),
            'snab_staff_id' => $request->get_param('snab_staff_id'),
            'display_order' => $request->get_param('display_order'),
        ], static fn (mixed $v): bool => $v !== null);

        try {
            $profileId = $this->profileService->saveProfile($agentMlsId, $data);
            return ApiResponse::success(['profile_id' => $profileId]);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * POST /agents/{agent_mls_id}/link-user — Link MLS agent to WP user (admin).
     */
    public function linkUser(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'user_id' => ['type' => 'integer', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $agentMlsId = (string) $request->get_param('agent_mls_id');
        $userId = (int) $request->get_param('user_id');

        try {
            $profileId = $this->profileService->linkToUser($agentMlsId, $userId);
            return ApiResponse::success(['profile_id' => $profileId, 'user_id' => $userId]);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }
}

<?php

declare(strict_types=1);

namespace BMN\Agents\Api\Controllers;

use BMN\Agents\Service\ReferralService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

final class ReferralController extends RestController
{
    protected string $resource = '';

    private readonly ReferralService $referralService;

    public function __construct(
        ReferralService $referralService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->referralService = $referralService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => 'agent/referral',
                'method'   => 'GET',
                'callback' => 'getReferral',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/referral',
                'method'   => 'POST',
                'callback' => 'setReferralCode',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/referral/regenerate',
                'method'   => 'POST',
                'callback' => 'regenerateCode',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/referral/stats',
                'method'   => 'GET',
                'callback' => 'getStats',
                'auth'     => true,
            ],
        ];
    }

    /**
     * GET /agent/referral — Get agent's referral code + URL + stats.
     */
    public function getReferral(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $referral = $this->referralService->getAgentReferral((int) $user->ID);

        return ApiResponse::success($referral);
    }

    /**
     * POST /agent/referral — Set/update custom referral code.
     */
    public function setReferralCode(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'code' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $code = (string) $request->get_param('code');

        try {
            $newCode = $this->referralService->updateCode((int) $user->ID, $code);
            return ApiResponse::success(['referral_code' => $newCode]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /agent/referral/regenerate — Generate new code.
     */
    public function regenerateCode(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        try {
            $code = $this->referralService->generateCode((int) $user->ID);
            return ApiResponse::success(['referral_code' => $code]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /agent/referral/stats — Detailed referral statistics.
     */
    public function getStats(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $stats = $this->referralService->getReferralStats((int) $user->ID);

        return ApiResponse::success($stats);
    }
}

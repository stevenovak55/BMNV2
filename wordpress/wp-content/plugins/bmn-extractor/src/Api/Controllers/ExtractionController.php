<?php

declare(strict_types=1);

namespace BMN\Extractor\Api\Controllers;

use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\PropertyRepository;
use BMN\Extractor\Service\ExtractionEngine;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for extraction management.
 *
 * Endpoints:
 *   GET  /bmn/v1/extractions/status  — current extraction state
 *   POST /bmn/v1/extractions/trigger — manual trigger (admin only)
 *   GET  /bmn/v1/extractions/history — recent run history
 *   GET  /bmn/v1/extractions/stats   — property counts and metrics
 */
final class ExtractionController extends RestController
{
    protected string $resource = 'extractions';

    private ExtractionEngine $engine;
    private ExtractionRepository $extractions;
    private PropertyRepository $properties;

    public function __construct(
        ExtractionEngine $engine,
        ExtractionRepository $extractions,
        PropertyRepository $properties,
    ) {
        parent::__construct();
        $this->engine = $engine;
        $this->extractions = $extractions;
        $this->properties = $properties;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path' => '/status',
                'method' => 'GET',
                'callback' => 'status',
                'auth' => false,
            ],
            [
                'path' => '/trigger',
                'method' => 'POST',
                'callback' => 'trigger',
                'auth' => false,
                'params' => [
                    'type' => [
                        'type' => 'string',
                        'default' => 'incremental',
                        'enum' => ['incremental', 'full'],
                    ],
                ],
            ],
            [
                'path' => '/history',
                'method' => 'GET',
                'callback' => 'history',
                'auth' => false,
                'params' => [
                    'limit' => [
                        'type' => 'integer',
                        'default' => 20,
                    ],
                ],
            ],
            [
                'path' => '/stats',
                'method' => 'GET',
                'callback' => 'stats',
                'auth' => false,
            ],
        ];
    }

    /**
     * GET /bmn/v1/extractions/status
     */
    public function status(WP_REST_Request $request): WP_REST_Response
    {
        $lastRun = $this->extractions->getLastRun();
        $isRunning = $this->extractions->isRunning();

        return ApiResponse::success([
            'is_running' => $isRunning,
            'last_run' => $lastRun ? [
                'id' => (int) $lastRun->id,
                'type' => $lastRun->extraction_type,
                'status' => $lastRun->status,
                'triggered_by' => $lastRun->triggered_by,
                'listings_processed' => (int) $lastRun->listings_processed,
                'listings_created' => (int) $lastRun->listings_created,
                'listings_updated' => (int) $lastRun->listings_updated,
                'errors_count' => (int) $lastRun->errors_count,
                'started_at' => $lastRun->started_at,
                'completed_at' => $lastRun->completed_at,
            ] : null,
        ]);
    }

    /**
     * POST /bmn/v1/extractions/trigger
     */
    public function trigger(WP_REST_Request $request): WP_REST_Response
    {
        if (! current_user_can('manage_options')) {
            return ApiResponse::error('Insufficient permissions.', 403);
        }

        $type = $request->get_param('type') ?? 'incremental';
        $isResync = ($type === 'full');

        try {
            $result = $this->engine->run(isResync: $isResync, triggeredBy: 'manual');

            return ApiResponse::success([
                'extraction_id' => $result['extraction_id'],
                'status' => $result['status'],
                'processed' => $result['processed'],
                'created' => $result['created'],
                'updated' => $result['updated'],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /bmn/v1/extractions/history
     */
    public function history(WP_REST_Request $request): WP_REST_Response
    {
        $limit = (int) ($request->get_param('limit') ?? 20);
        $runs = $this->extractions->getHistory($limit);

        $data = array_map(fn(object $run) => [
            'id' => (int) $run->id,
            'type' => $run->extraction_type,
            'status' => $run->status,
            'triggered_by' => $run->triggered_by,
            'listings_processed' => (int) $run->listings_processed,
            'listings_created' => (int) $run->listings_created,
            'listings_updated' => (int) $run->listings_updated,
            'errors_count' => (int) $run->errors_count,
            'started_at' => $run->started_at,
            'completed_at' => $run->completed_at,
            'error_message' => $run->error_message,
        ], $runs);

        return ApiResponse::success($data);
    }

    /**
     * GET /bmn/v1/extractions/stats
     */
    public function stats(WP_REST_Request $request): WP_REST_Response
    {
        $statusCounts = $this->properties->countByStatus();
        $totalProperties = array_sum($statusCounts);
        $lastModified = $this->properties->getLastModificationTimestamp();

        return ApiResponse::success([
            'total_properties' => $totalProperties,
            'by_status' => $statusCounts,
            'last_modification' => $lastModified,
        ]);
    }
}

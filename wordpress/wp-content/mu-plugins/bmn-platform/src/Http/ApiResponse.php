<?php

declare(strict_types=1);

namespace BMN\Platform\Http;

use WP_REST_Response;

/**
 * Unified API response helper.
 *
 * Ensures every BMN REST endpoint returns a consistent JSON envelope:
 *
 *     {
 *         "success": true|false,
 *         "data":    { ... },
 *         "meta":    { ... }
 *     }
 */
final class ApiResponse
{
    /**
     * Build a successful response.
     *
     * @param mixed               $data Payload (array, object, scalar, etc.).
     * @param array<string,mixed> $meta Optional metadata (timing, cache info, etc.).
     * @param int                 $code HTTP status code (default 200).
     */
    public static function success(mixed $data, array $meta = [], int $code = 200): WP_REST_Response
    {
        $body = [
            'success' => true,
            'data'    => $data,
        ];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new WP_REST_Response($body, $code);
    }

    /**
     * Build an error response.
     *
     * @param string              $message Human-readable error message.
     * @param int                 $code    HTTP status code (default 400).
     * @param array<string,mixed> $details Optional structured error details.
     */
    public static function error(string $message, int $code = 400, array $details = []): WP_REST_Response
    {
        $body = [
            'success' => false,
            'data'    => null,
            'meta'    => [
                'error'   => $message,
                'code'    => $code,
            ],
        ];

        if ($details !== []) {
            $body['meta']['details'] = $details;
        }

        return new WP_REST_Response($body, $code);
    }

    /**
     * Build a paginated success response.
     *
     * @param array<int,mixed> $data    The current page of results.
     * @param int              $total   Total number of matching records.
     * @param int              $page    Current page number (1-based).
     * @param int              $perPage Number of items per page.
     * @param array<string,mixed> $extraMeta Additional metadata to merge.
     */
    public static function paginated(
        array $data,
        int $total,
        int $page,
        int $perPage,
        array $extraMeta = [],
    ): WP_REST_Response {
        $totalPages = ($perPage > 0) ? (int) ceil($total / $perPage) : 0;

        $meta = array_merge([
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ], $extraMeta);

        $response = self::success($data, $meta);

        // Set WP pagination headers for compatibility with standard clients.
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) $totalPages);

        return $response;
    }
}

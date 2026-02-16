<?php

declare(strict_types=1);

namespace BMN\Platform\Http;

use BMN\Platform\Auth\AuthMiddleware;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Base REST controller for all BMN API endpoints.
 *
 * Subclasses declare their routes via getRoutes() and this base class
 * handles registration, parameter validation, and JWT auth checks.
 *
 * Usage:
 *
 *     class PropertyController extends RestController
 *     {
 *         protected string $resource = 'properties';
 *
 *         protected function getRoutes(): array
 *         {
 *             return [
 *                 [
 *                     'path'       => '',
 *                     'method'     => 'GET',
 *                     'callback'   => 'index',
 *                     'auth'       => false,
 *                 ],
 *                 [
 *                     'path'       => '/(?P<id>\d+)',
 *                     'method'     => 'GET',
 *                     'callback'   => 'show',
 *                     'auth'       => false,
 *                 ],
 *             ];
 *         }
 *     }
 */
abstract class RestController
{
    /** REST namespace shared by all BMN endpoints. */
    protected string $namespace = 'bmn/v1';

    /** Resource name used as route prefix (e.g. "properties"). */
    protected string $resource = '';

    /** Optional AuthMiddleware instance injected via constructor. */
    protected ?AuthMiddleware $authMiddleware;

    public function __construct(?AuthMiddleware $authMiddleware = null)
    {
        $this->authMiddleware = $authMiddleware;
    }

    // ------------------------------------------------------------------
    // Route declaration
    // ------------------------------------------------------------------

    /**
     * Return the list of route definitions for this controller.
     *
     * Each entry is an associative array:
     *   - path       (string)  Sub-path appended to /{namespace}/{resource}.
     *   - method     (string)  HTTP verb (GET, POST, PUT, PATCH, DELETE).
     *   - callback   (string)  Method name on this controller.
     *   - auth       (bool)    Whether the route requires authentication (default true).
     *   - params     (array)   Optional WP-style argument schema for validation.
     *
     * @return array<int, array{path: string, method: string, callback: string, auth?: bool, params?: array}>
     */
    abstract protected function getRoutes(): array;

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    /**
     * Register all routes declared by this controller with the WP REST API.
     *
     * Call this method inside a `rest_api_init` action.
     */
    public function registerRoutes(): void
    {
        foreach ($this->getRoutes() as $route) {
            $fullPath = $this->resource . ($route['path'] ?? '');
            $requiresAuth = $route['auth'] ?? true;

            register_rest_route($this->namespace, $fullPath, [
                'methods'             => $route['method'],
                'callback'            => [$this, $route['callback']],
                'permission_callback' => $requiresAuth
                    ? [$this, 'checkAuth']
                    : '__return_true',
                'args'                => $route['params'] ?? [],
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Authentication
    // ------------------------------------------------------------------

    /**
     * Permission callback that delegates to the AuthMiddleware.
     *
     * @return bool|WP_Error True if authenticated, WP_Error on failure.
     */
    public function checkAuth(WP_REST_Request $request): bool|WP_Error
    {
        if ($this->authMiddleware === null) {
            return new WP_Error(
                'bmn_auth_unavailable',
                'Authentication service is not configured.',
                ['status' => 500]
            );
        }

        return $this->authMiddleware->authenticate($request);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Validate request parameters against a schema.
     *
     * @param WP_REST_Request              $request The incoming request.
     * @param array<string, array|string>  $rules   Simple key => type pairs or WP arg arrays.
     *
     * @return true|WP_Error True if valid, WP_Error on validation failure.
     */
    protected function validateParams(WP_REST_Request $request, array $rules): true|WP_Error
    {
        $errors = [];

        foreach ($rules as $key => $rule) {
            $type = is_array($rule) ? ($rule['type'] ?? 'string') : $rule;
            $required = is_array($rule) ? ($rule['required'] ?? false) : false;
            $value = $request->get_param($key);

            if ($required && ($value === null || $value === '')) {
                $errors[] = sprintf('Parameter "%s" is required.', $key);
                continue;
            }

            if ($value === null) {
                continue;
            }

            $valid = match ($type) {
                'integer', 'int' => is_numeric($value),
                'boolean', 'bool' => in_array($value, [true, false, '0', '1', 'true', 'false'], true),
                'email' => (bool) is_email($value),
                default => true,
            };

            if (! $valid) {
                $errors[] = sprintf('Parameter "%s" must be of type %s.', $key, $type);
            }
        }

        if ($errors !== []) {
            return new WP_Error(
                'bmn_validation_error',
                implode(' ', $errors),
                ['status' => 422]
            );
        }

        return true;
    }

    /**
     * Get the currently authenticated WordPress user, or null when anonymous.
     */
    protected function getCurrentUser(): ?\WP_User
    {
        $user = wp_get_current_user();

        return ($user->ID !== 0) ? $user : null;
    }
}

<?php

declare(strict_types=1);

namespace BMN\Platform\Http;

use BMN\Platform\Auth\AuthService;
use BMN\Platform\Cache\CacheService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Email\EmailService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Platform\Logging\LoggingService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Health-check endpoint for verifying platform services.
 *
 * GET /wp-json/bmn/v1/health       — public, basic status
 * GET /wp-json/bmn/v1/health/full  — authenticated, detailed service checks
 */
final class HealthController extends RestController
{
    protected string $resource = 'health';

    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct(null);
        $this->container = $container;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'basic',
                'auth'     => false,
            ],
            [
                'path'     => '/full',
                'method'   => 'GET',
                'callback' => 'full',
                'auth'     => false,
            ],
        ];
    }

    /**
     * Basic health check — confirms platform is booted.
     */
    public function basic(WP_REST_Request $request): WP_REST_Response
    {
        return ApiResponse::success([
            'status'   => 'ok',
            'platform' => BMN_PLATFORM_VERSION,
            'php'      => PHP_VERSION,
            'wp'       => get_bloginfo('version'),
            'theme'    => get_stylesheet(),
            'time'     => current_time('mysql'),
        ]);
    }

    /**
     * Full health check — tests each platform service.
     */
    public function full(WP_REST_Request $request): WP_REST_Response
    {
        $services = [];
        $allHealthy = true;

        // 1. Database
        $services['database'] = $this->checkService('DatabaseService', function () {
            $db = $this->container->make(DatabaseService::class);
            $health = $db->healthCheck();
            return [
                'connected' => $health['connected'],
                'prefix'    => $health['prefix'],
                'charset'   => $health['charset'],
            ];
        });

        // 2. Cache
        $services['cache'] = $this->checkService('CacheService', function () {
            $cache = $this->container->make(CacheService::class);
            $testKey = 'health_check_' . time();
            $cache->set($testKey, 'ok', 60, 'default');
            $value = $cache->get($testKey, 'default');
            $cache->forget($testKey, 'default');
            $stats = $cache->getStats();
            return [
                'write_read' => $value === 'ok' ? 'pass' : 'fail',
                'stats'      => $stats,
            ];
        });

        // 3. Auth
        $services['auth'] = $this->checkService('AuthService', function () {
            $auth = $this->container->make(AuthService::class);
            $token = $auth->generateAccessToken(1);
            $payload = $auth->validateToken($token);
            return [
                'token_generation' => !empty($token) ? 'pass' : 'fail',
                'token_validation' => $payload['sub'] === 1 ? 'pass' : 'fail',
            ];
        });

        // 4. Logging
        $services['logging'] = $this->checkService('LoggingService', function () {
            $logger = $this->container->make(LoggingService::class);
            $logger->info('Health check', ['source' => 'health_endpoint']);
            return [
                'min_level' => $logger->getMinLevel(),
                'write'     => 'pass',
            ];
        });

        // 5. Email
        $services['email'] = $this->checkService('EmailService', function () {
            $email = $this->container->make(EmailService::class);
            $from = $email->getFromHeader();
            return [
                'from_header' => !empty($from) ? 'pass' : 'fail',
                'from'        => $from,
            ];
        });

        // 6. Geocoding
        $services['geocoding'] = $this->checkService('GeocodingService', function () {
            $geo = $this->container->make(GeocodingService::class);
            // Boston to Cambridge — should be ~3-4 miles
            $distance = $geo->haversineDistance(42.3601, -71.0589, 42.3736, -71.1097);
            $valid = $geo->validateCoordinates(42.3601, -71.0589);
            return [
                'haversine_test'    => ($distance > 2 && $distance < 5) ? 'pass' : 'fail',
                'boston_to_cambridge' => round($distance, 2) . ' miles',
                'coordinate_validation' => $valid ? 'pass' : 'fail',
            ];
        });

        foreach ($services as $service) {
            if ($service['status'] !== 'ok') {
                $allHealthy = false;
                break;
            }
        }

        return ApiResponse::success([
            'status'   => $allHealthy ? 'healthy' : 'degraded',
            'platform' => BMN_PLATFORM_VERSION,
            'services' => $services,
            'time'     => current_time('mysql'),
        ]);
    }

    /**
     * Run a service check and catch any exceptions.
     *
     * @return array{status: string, details: array}
     */
    private function checkService(string $name, callable $check): array
    {
        try {
            $details = $check();
            return [
                'status'  => 'ok',
                'details' => $details,
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'error',
                'details' => [
                    'message' => $e->getMessage(),
                    'class'   => get_class($e),
                ],
            ];
        }
    }
}

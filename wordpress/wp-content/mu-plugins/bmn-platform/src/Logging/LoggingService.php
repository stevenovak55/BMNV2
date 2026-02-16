<?php

declare(strict_types=1);

namespace BMN\Platform\Logging;

/**
 * Concrete logging service for the BMN Platform.
 *
 * Provides structured logging with severity levels, context data,
 * activity tracking to the database, and performance monitoring.
 */
class LoggingService
{
    public const DEBUG   = 0;
    public const INFO    = 1;
    public const WARNING = 2;
    public const ERROR   = 3;

    private int $minLevel;

    /**
     * Construct the logging service.
     *
     * Minimum log level is determined from the environment:
     * - WP_DEBUG enabled  -> DEBUG
     * - WP_ENV = staging  -> INFO
     * - Default (production) -> WARNING
     */
    public function __construct()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->minLevel = self::DEBUG;
        } elseif (defined('WP_ENV') && WP_ENV === 'staging') {
            $this->minLevel = self::INFO;
        } else {
            $this->minLevel = self::WARNING;
        }
    }

    /**
     * Log a message at DEBUG level.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log a message at INFO level.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log a message at WARNING level.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log a message at ERROR level.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Core log method.
     *
     * Writes a formatted log entry via error_log(). Entries below
     * the current minimum level are silently discarded.
     *
     * Format: [Y-m-d H:i:s] BMN {LEVEL}: {message} | Context: {json}
     */
    public function log(int $level, string $message, array $context = []): void
    {
        if ($level < $this->minLevel) {
            return;
        }

        $timestamp = current_time('mysql');
        $levelName = $this->getLevelName($level);
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $formatted = "[{$timestamp}] BMN {$levelName}: {$message} | Context: {$contextJson}";

        error_log($formatted);
    }

    /**
     * Log a user activity to the bmn_activity_log database table.
     *
     * Records structured activity data including the acting user,
     * the action performed, the target entity, client IP, user agent,
     * and any additional context.
     */
    public function logActivity(
        int $userId,
        string $action,
        string $entityType = '',
        int|string $entityId = '',
        array $context = [],
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'bmn_activity_log';

        $wpdb->insert(
            $table,
            [
                'user_id'     => $userId,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => (string) $entityId,
                'ip_address'  => $this->getClientIp(),
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'context'     => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at'  => current_time('mysql'),
            ],
            [
                '%d', // user_id
                '%s', // action
                '%s', // entity_type
                '%s', // entity_id
                '%s', // ip_address
                '%s', // user_agent
                '%s', // context
                '%s', // created_at
            ],
        );
    }

    /**
     * Detect the client IP address with CDN awareness.
     *
     * Checks headers in order of priority:
     * 1. Cloudflare CF-Connecting-IP
     * 2. X-Forwarded-For (first IP in comma-separated list)
     * 3. REMOTE_ADDR
     * 4. Fallback to '0.0.0.0'
     *
     * All candidates are validated with FILTER_VALIDATE_IP.
     */
    public function getClientIp(): string
    {
        $candidates = [];

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidates[] = trim($forwarded[0]);
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = trim($_SERVER['REMOTE_ADDR']);
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * Log performance data for an operation.
     *
     * Automatically appends duration_ms and memory_usage to the context.
     * Operations exceeding 1.0 second are logged at WARNING level;
     * all others are logged at INFO level.
     */
    public function performance(string $operation, float $durationSeconds, array $context = []): void
    {
        $context['duration_ms']  = round($durationSeconds * 1000, 2);
        $context['memory_usage'] = memory_get_usage(true);

        $level = $durationSeconds > 1.0 ? self::WARNING : self::INFO;

        $this->log($level, "Performance: {$operation}", $context);
    }

    /**
     * Return the current minimum log level.
     */
    public function getMinLevel(): int
    {
        return $this->minLevel;
    }

    /**
     * Map a numeric log level to its human-readable name.
     */
    private function getLevelName(int $level): string
    {
        return match ($level) {
            self::DEBUG   => 'DEBUG',
            self::INFO    => 'INFO',
            self::WARNING => 'WARNING',
            self::ERROR   => 'ERROR',
            default       => 'UNKNOWN',
        };
    }
}

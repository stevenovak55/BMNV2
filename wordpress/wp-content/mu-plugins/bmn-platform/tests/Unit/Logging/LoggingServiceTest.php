<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Logging;

use BMN\Platform\Logging\LoggingService;
use PHPUnit\Framework\TestCase;

class LoggingServiceTest extends TestCase
{
    private LoggingService $service;

    /** @var array<string, mixed> Saved $_SERVER state. */
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->service = new LoggingService();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    // -----------------------------------------------------------------
    //  Basic log methods do not throw
    // -----------------------------------------------------------------

    public function testDebugLogDoesNotThrow(): void
    {
        @$this->service->debug('Debug message', ['key' => 'value']);
        $this->assertTrue(true); // Reached without exception.
    }

    public function testInfoLogDoesNotThrow(): void
    {
        @$this->service->info('Info message');
        $this->assertTrue(true);
    }

    public function testWarningLogDoesNotThrow(): void
    {
        @$this->service->warning('Warning message');
        $this->assertTrue(true);
    }

    public function testErrorLogDoesNotThrow(): void
    {
        @$this->service->error('Error message');
        $this->assertTrue(true);
    }

    // -----------------------------------------------------------------
    //  Min level
    // -----------------------------------------------------------------

    public function testMinLevelIsDebugWhenWpDebugTrue(): void
    {
        // Bootstrap defines WP_DEBUG = true, so min level should be DEBUG (0).
        $this->assertSame(LoggingService::DEBUG, $this->service->getMinLevel());
    }

    // -----------------------------------------------------------------
    //  getClientIp()
    // -----------------------------------------------------------------

    public function testGetClientIpReturnsRemoteAddr(): void
    {
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $ip = $this->service->getClientIp();

        $this->assertSame('192.168.1.100', $ip);
    }

    public function testGetClientIpReturnsCloudflareIp(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $ip = $this->service->getClientIp();

        $this->assertSame('203.0.113.50', $ip);
    }

    public function testGetClientIpReturnsXForwardedFor(): void
    {
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 10.0.0.2';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $ip = $this->service->getClientIp();

        $this->assertSame('10.0.0.1', $ip);
    }

    public function testGetClientIpReturnsFallback(): void
    {
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['REMOTE_ADDR']);

        $ip = $this->service->getClientIp();

        $this->assertSame('0.0.0.0', $ip);
    }

    // -----------------------------------------------------------------
    //  performance()
    // -----------------------------------------------------------------

    public function testPerformanceLogsWithDuration(): void
    {
        // Performance logging calls log() internally which calls error_log().
        // Suppress output; we just verify it does not throw.
        @$this->service->performance('db_query', 0.250, ['query' => 'SELECT 1']);
        $this->assertTrue(true);
    }

    // -----------------------------------------------------------------
    //  logActivity()
    // -----------------------------------------------------------------

    public function testLogActivityInsertsToDatabase(): void
    {
        $wpdb = new \wpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->service->logActivity(
            userId: 5,
            action: 'login',
            entityType: 'user',
            entityId: 5,
            context: ['source' => 'web'],
        );

        // The wpdb stub records queries in its $queries array.
        $this->assertNotEmpty($wpdb->queries);

        $lastQuery = end($wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_activity_log', $lastQuery['sql']);
        $this->assertSame(5, $lastQuery['args']['user_id']);
        $this->assertSame('login', $lastQuery['args']['action']);
        $this->assertSame('user', $lastQuery['args']['entity_type']);
        $this->assertSame('5', $lastQuery['args']['entity_id']);
        $this->assertSame('127.0.0.1', $lastQuery['args']['ip_address']);
        $this->assertSame('PHPUnit', $lastQuery['args']['user_agent']);

        unset($GLOBALS['wpdb']);
    }

    // -----------------------------------------------------------------
    //  Level constants
    // -----------------------------------------------------------------

    public function testLevelConstants(): void
    {
        $this->assertSame(0, LoggingService::DEBUG);
        $this->assertSame(1, LoggingService::INFO);
        $this->assertSame(2, LoggingService::WARNING);
        $this->assertSame(3, LoggingService::ERROR);

        // Verify strict ordering.
        $this->assertLessThan(LoggingService::INFO, LoggingService::DEBUG);
        $this->assertLessThan(LoggingService::WARNING, LoggingService::INFO);
        $this->assertLessThan(LoggingService::ERROR, LoggingService::WARNING);
    }
}

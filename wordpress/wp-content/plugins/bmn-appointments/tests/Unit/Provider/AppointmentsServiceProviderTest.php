<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Provider;

use BMN\Appointments\Api\Controllers\AppointmentController;
use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Calendar\NullCalendarService;
use BMN\Appointments\Notification\AppointmentNotificationService;
use BMN\Appointments\Provider\AppointmentsServiceProvider;
use BMN\Appointments\Repository\AppointmentRepository;
use BMN\Appointments\Repository\AppointmentTypeRepository;
use BMN\Appointments\Repository\AttendeeRepository;
use BMN\Appointments\Repository\AvailabilityRuleRepository;
use BMN\Appointments\Repository\NotificationLogRepository;
use BMN\Appointments\Repository\StaffRepository;
use BMN\Appointments\Repository\StaffServiceRepository;
use BMN\Appointments\Service\AppointmentService;
use BMN\Appointments\Service\AvailabilityService;
use BMN\Appointments\Service\StaffService;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use PHPUnit\Framework\TestCase;

final class AppointmentsServiceProviderTest extends TestCase
{
    private Container $container;
    private AppointmentsServiceProvider $provider;

    protected function setUp(): void
    {
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_rest_routes'] = [];

        $this->container = new Container();
        $this->provider = new AppointmentsServiceProvider();

        // Register platform service stubs.
        // DatabaseService is final so we cannot mock it; use an anonymous stub.
        $wpdb = new \wpdb();

        $dbService = new class($wpdb) {
            private \wpdb $wpdb;
            public function __construct(\wpdb $wpdb) { $this->wpdb = $wpdb; }
            public function getWpdb(): \wpdb { return $this->wpdb; }
        };

        $this->container->instance(\BMN\Platform\Database\DatabaseService::class, $dbService);
        // AuthMiddleware is also final; use an anonymous stub.
        $authMiddleware = new class {
            public function authenticate(\WP_REST_Request $request): ?\WP_User { return null; }
            public function authenticateOptional(\WP_REST_Request $request): ?\WP_User { return null; }
        };
        $this->container->instance(\BMN\Platform\Auth\AuthMiddleware::class, $authMiddleware);
        $this->container->instance(\BMN\Platform\Email\EmailService::class, $this->createMock(\BMN\Platform\Email\EmailService::class));
    }

    public function testExtendsServiceProvider(): void
    {
        $this->assertInstanceOf(ServiceProvider::class, $this->provider);
    }

    public function testRegisterBindsAllRepositories(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(StaffRepository::class));
        $this->assertTrue($this->container->has(AppointmentTypeRepository::class));
        $this->assertTrue($this->container->has(AvailabilityRuleRepository::class));
        $this->assertTrue($this->container->has(AppointmentRepository::class));
        $this->assertTrue($this->container->has(AttendeeRepository::class));
        $this->assertTrue($this->container->has(StaffServiceRepository::class));
        $this->assertTrue($this->container->has(NotificationLogRepository::class));
    }

    public function testRegisterBindsCalendarAsNullService(): void
    {
        $this->provider->register($this->container);

        $calendarService = $this->container->make(GoogleCalendarService::class);
        $this->assertInstanceOf(NullCalendarService::class, $calendarService);
    }

    public function testRegisterBindsAllServices(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(StaffService::class));
        $this->assertTrue($this->container->has(AvailabilityService::class));
        $this->assertTrue($this->container->has(AppointmentService::class));
        $this->assertTrue($this->container->has(AppointmentNotificationService::class));
    }

    public function testRegisterBindsController(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(AppointmentController::class));
    }

    public function testBootRegistersRestApiInitAction(): void
    {
        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['rest_api_init']);
    }

    public function testBootRegistersReminderCronAction(): void
    {
        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('bmn_appointments_send_reminders', $GLOBALS['wp_actions']);
    }
}

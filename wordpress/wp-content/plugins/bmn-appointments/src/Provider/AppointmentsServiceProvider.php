<?php

declare(strict_types=1);

namespace BMN\Appointments\Provider;

use BMN\Appointments\Api\Controllers\AppointmentController;
use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Calendar\NullCalendarService;
use BMN\Appointments\Notification\AppointmentNotificationService;
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
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Email\EmailService;

/**
 * Service provider for the BMN Appointments plugin.
 *
 * Follows the UsersServiceProvider pattern:
 *   register() — bind all services as singletons
 *   boot()     — register REST routes and cron
 */
final class AppointmentsServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Resolve platform services.
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $authMiddleware = $container->make(AuthMiddleware::class);
        $emailService = $container->make(EmailService::class);

        // Repositories.
        $container->singleton(StaffRepository::class, static fn (): StaffRepository => new StaffRepository($wpdb));

        $container->singleton(AppointmentTypeRepository::class, static fn (): AppointmentTypeRepository => new AppointmentTypeRepository($wpdb));

        $container->singleton(AvailabilityRuleRepository::class, static fn (): AvailabilityRuleRepository => new AvailabilityRuleRepository($wpdb));

        $container->singleton(AppointmentRepository::class, static fn (): AppointmentRepository => new AppointmentRepository($wpdb));

        $container->singleton(AttendeeRepository::class, static fn (): AttendeeRepository => new AttendeeRepository($wpdb));

        $container->singleton(StaffServiceRepository::class, static fn (): StaffServiceRepository => new StaffServiceRepository($wpdb));

        $container->singleton(NotificationLogRepository::class, static fn (): NotificationLogRepository => new NotificationLogRepository($wpdb));

        // Google Calendar (NullCalendarService by default, swappable later).
        $container->singleton(GoogleCalendarService::class, static fn (): GoogleCalendarService => new NullCalendarService());

        // Services.
        $container->singleton(StaffService::class, static fn (Container $c): StaffService => new StaffService(
            $c->make(StaffRepository::class),
            $c->make(StaffServiceRepository::class),
        ));

        $container->singleton(AvailabilityService::class, static fn (Container $c): AvailabilityService => new AvailabilityService(
            $c->make(StaffRepository::class),
            $c->make(AppointmentTypeRepository::class),
            $c->make(AvailabilityRuleRepository::class),
            $c->make(AppointmentRepository::class),
            $c->make(GoogleCalendarService::class),
        ));

        $container->singleton(AppointmentNotificationService::class, static fn (Container $c): AppointmentNotificationService => new AppointmentNotificationService(
            $emailService,
            $c->make(AppointmentRepository::class),
            $c->make(AttendeeRepository::class),
            $c->make(NotificationLogRepository::class),
        ));

        $container->singleton(AppointmentService::class, static fn (Container $c): AppointmentService => new AppointmentService(
            $c->make(AppointmentRepository::class),
            $c->make(AppointmentTypeRepository::class),
            $c->make(AttendeeRepository::class),
            $c->make(StaffRepository::class),
            $c->make(AvailabilityService::class),
            $c->make(GoogleCalendarService::class),
            $c->make(AppointmentNotificationService::class),
        ));

        // Controller.
        $container->singleton(AppointmentController::class, static fn (Container $c): AppointmentController => new AppointmentController(
            $c->make(AppointmentService::class),
            $c->make(AvailabilityService::class),
            $c->make(StaffService::class),
            $c->make(AppointmentTypeRepository::class),
            $authMiddleware,
        ));
    }

    public function boot(Container $container): void
    {
        // Register REST routes.
        add_action('rest_api_init', static function () use ($container): void {
            $container->make(AppointmentController::class)->registerRoutes();
        });

        // Schedule reminder cron (hourly). Uses time() not current_time('timestamp').
        if (!wp_next_scheduled('bmn_appointments_send_reminders')) {
            wp_schedule_event(time(), 'hourly', 'bmn_appointments_send_reminders');
        }

        add_action('bmn_appointments_send_reminders', static function () use ($container): void {
            $container->make(AppointmentNotificationService::class)->processReminders();
        });
    }
}

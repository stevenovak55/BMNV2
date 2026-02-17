<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Notification;

use BMN\Appointments\Notification\AppointmentNotificationService;
use BMN\Appointments\Repository\AppointmentRepository;
use BMN\Appointments\Repository\AttendeeRepository;
use BMN\Appointments\Repository\NotificationLogRepository;
use BMN\Platform\Email\EmailService;
use PHPUnit\Framework\TestCase;

final class AppointmentNotificationServiceTest extends TestCase
{
    private EmailService $emailService;
    private AppointmentRepository $appointmentRepo;
    private AttendeeRepository $attendeeRepo;
    private NotificationLogRepository $logRepo;
    private AppointmentNotificationService $service;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->appointmentRepo = $this->createMock(AppointmentRepository::class);
        $this->attendeeRepo = $this->createMock(AttendeeRepository::class);
        $this->logRepo = $this->createMock(NotificationLogRepository::class);

        $this->service = new AppointmentNotificationService(
            $this->emailService,
            $this->appointmentRepo,
            $this->attendeeRepo,
            $this->logRepo,
        );
    }

    private function makeAppointment(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'client_name' => 'John',
            'client_email' => 'john@test.com',
            'appointment_date' => '2026-03-02',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'type_name' => 'Showing',
            'staff_name' => 'Steve',
            'staff_email' => 'steve@test.com',
        ], $overrides);
    }

    public function testSendConfirmationSendsClientAndStaffEmails(): void
    {
        $this->appointmentRepo->method('find')->willReturn($this->makeAppointment());

        $this->emailService->expects($this->exactly(2))
            ->method('sendTemplate')
            ->willReturn(true);

        $this->logRepo->expects($this->exactly(2))
            ->method('logNotification');

        $this->service->sendConfirmation(1);
    }

    public function testSendConfirmationSkipsWhenNotFound(): void
    {
        $this->appointmentRepo->method('find')->willReturn(null);

        $this->emailService->expects($this->never())
            ->method('sendTemplate');

        $this->service->sendConfirmation(999);
    }

    public function testSendConfirmationLogsFailure(): void
    {
        $this->appointmentRepo->method('find')->willReturn($this->makeAppointment());

        $this->emailService->method('sendTemplate')->willReturn(false);

        $this->logRepo->expects($this->exactly(2))
            ->method('logNotification')
            ->with(
                $this->anything(),
                'confirmation',
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'failed',
                'Email delivery failed',
            );

        $this->service->sendConfirmation(1);
    }

    public function testSendCancellationSendsClientAndStaffEmails(): void
    {
        $this->appointmentRepo->method('find')->willReturn($this->makeAppointment());

        $this->emailService->expects($this->exactly(2))
            ->method('sendTemplate')
            ->willReturn(true);

        $this->logRepo->expects($this->exactly(2))
            ->method('logNotification');

        $this->service->sendCancellation(1, 'Changed plans');
    }

    public function testSendCancellationSkipsWhenNotFound(): void
    {
        $this->appointmentRepo->method('find')->willReturn(null);

        $this->emailService->expects($this->never())->method('sendTemplate');

        $this->service->sendCancellation(999, 'test');
    }

    public function testSendRescheduleSendsClientAndStaffEmails(): void
    {
        $this->appointmentRepo->method('find')->willReturn($this->makeAppointment());

        $this->emailService->expects($this->exactly(2))
            ->method('sendTemplate')
            ->willReturn(true);

        $this->service->sendReschedule(1, '2026-03-01', '09:00:00');
    }

    public function testSendConfirmationSkipsStaffEmailWhenMissing(): void
    {
        $appointment = $this->makeAppointment(['staff_email' => '']);
        $this->appointmentRepo->method('find')->willReturn($appointment);

        // Should only send 1 email (client only).
        $this->emailService->expects($this->once())
            ->method('sendTemplate')
            ->willReturn(true);

        $this->service->sendConfirmation(1);
    }

    public function testProcessReminders24hSendsReminders(): void
    {
        $this->appointmentRepo->method('findDueReminders24h')->willReturn([
            $this->makeAppointment(),
        ]);

        $this->attendeeRepo->method('findUnsentReminders24h')->willReturn([
            (object) ['id' => 1, 'name' => 'John', 'email' => 'john@test.com'],
        ]);

        $this->emailService->expects($this->once())
            ->method('sendTemplate')
            ->willReturn(true);

        $this->attendeeRepo->expects($this->once())
            ->method('markReminderSent')
            ->with(1, '24h');

        $this->service->processReminders();
    }

    public function testProcessReminders1hSendsReminders(): void
    {
        $this->appointmentRepo->method('findDueReminders24h')->willReturn([]);
        $this->appointmentRepo->method('findDueReminders1h')->willReturn([
            $this->makeAppointment(),
        ]);

        $this->attendeeRepo->method('findUnsentReminders24h')->willReturn([]);
        $this->attendeeRepo->method('findUnsentReminders1h')->willReturn([
            (object) ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com'],
        ]);

        $this->emailService->expects($this->once())
            ->method('sendTemplate')
            ->willReturn(true);

        $this->attendeeRepo->expects($this->once())
            ->method('markReminderSent')
            ->with(2, '1h');

        $this->service->processReminders();
    }

    public function testProcessRemindersNoopWhenEmpty(): void
    {
        $this->appointmentRepo->method('findDueReminders24h')->willReturn([]);
        $this->appointmentRepo->method('findDueReminders1h')->willReturn([]);

        $this->emailService->expects($this->never())->method('sendTemplate');

        $this->service->processReminders();
    }

    public function testSendConfirmationUsesAppointmentContext(): void
    {
        $this->appointmentRepo->method('find')->willReturn($this->makeAppointment());

        $this->emailService->expects($this->exactly(2))
            ->method('sendTemplate')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return ($options['context'] ?? '') === 'appointment';
                }),
            )
            ->willReturn(true);

        $this->service->sendConfirmation(1);
    }
}

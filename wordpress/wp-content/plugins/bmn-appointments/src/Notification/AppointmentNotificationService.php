<?php

declare(strict_types=1);

namespace BMN\Appointments\Notification;

use BMN\Appointments\Repository\AppointmentRepository;
use BMN\Appointments\Repository\AttendeeRepository;
use BMN\Appointments\Repository\NotificationLogRepository;
use BMN\Platform\Email\EmailService;

/**
 * Handles all appointment-related email notifications.
 *
 * Uses the platform EmailService with {{variable}} template interpolation.
 * All sends are logged to bmn_notifications_log. Email context = 'appointment'.
 */
class AppointmentNotificationService
{
    private readonly EmailService $emailService;
    private readonly AppointmentRepository $appointmentRepo;
    private readonly AttendeeRepository $attendeeRepo;
    private readonly NotificationLogRepository $logRepo;

    public function __construct(
        EmailService $emailService,
        AppointmentRepository $appointmentRepo,
        AttendeeRepository $attendeeRepo,
        NotificationLogRepository $logRepo,
    ) {
        $this->emailService = $emailService;
        $this->appointmentRepo = $appointmentRepo;
        $this->attendeeRepo = $attendeeRepo;
        $this->logRepo = $logRepo;
    }

    /**
     * Send confirmation emails to client and staff.
     */
    public function sendConfirmation(int $appointmentId): void
    {
        $appointment = $this->appointmentRepo->find($appointmentId);

        if ($appointment === null) {
            return;
        }

        $variables = $this->buildVariables($appointment);

        // Client confirmation.
        $this->sendAndLog(
            $appointmentId,
            'confirmation',
            'client',
            $appointment->client_email,
            'Appointment Confirmed - BMN Boston',
            '<p>Hi {{client_name}},</p>'
            . '<p>Your appointment has been confirmed:</p>'
            . '<ul>'
            . '<li><strong>Date:</strong> {{appointment_date}}</li>'
            . '<li><strong>Time:</strong> {{start_time}} - {{end_time}}</li>'
            . '<li><strong>Type:</strong> {{type_name}}</li>'
            . '<li><strong>With:</strong> {{staff_name}}</li>'
            . '</ul>'
            . '<p>If you need to cancel or reschedule, please do so at least {{cancel_hours}} hours before your appointment.</p>',
            $variables,
        );

        // Staff notification.
        if (!empty($appointment->staff_email)) {
            $this->sendAndLog(
                $appointmentId,
                'confirmation',
                'staff',
                $appointment->staff_email,
                'New Appointment - ' . $appointment->client_name,
                '<p>A new appointment has been booked:</p>'
                . '<ul>'
                . '<li><strong>Client:</strong> {{client_name}} ({{client_email}})</li>'
                . '<li><strong>Date:</strong> {{appointment_date}}</li>'
                . '<li><strong>Time:</strong> {{start_time}} - {{end_time}}</li>'
                . '<li><strong>Type:</strong> {{type_name}}</li>'
                . '</ul>',
                $variables,
            );
        }
    }

    /**
     * Send cancellation emails to client and staff.
     */
    public function sendCancellation(int $appointmentId, string $reason): void
    {
        $appointment = $this->appointmentRepo->find($appointmentId);

        if ($appointment === null) {
            return;
        }

        $variables = $this->buildVariables($appointment);
        $variables['cancellation_reason'] = $reason;

        // Client notification.
        $this->sendAndLog(
            $appointmentId,
            'cancellation',
            'client',
            $appointment->client_email,
            'Appointment Cancelled - BMN Boston',
            '<p>Hi {{client_name}},</p>'
            . '<p>Your appointment on {{appointment_date}} at {{start_time}} has been cancelled.</p>'
            . '<p><strong>Reason:</strong> {{cancellation_reason}}</p>'
            . '<p>If you would like to rebook, please visit our website or app.</p>',
            $variables,
        );

        // Staff notification.
        if (!empty($appointment->staff_email)) {
            $this->sendAndLog(
                $appointmentId,
                'cancellation',
                'staff',
                $appointment->staff_email,
                'Appointment Cancelled - ' . $appointment->client_name,
                '<p>An appointment has been cancelled:</p>'
                . '<ul>'
                . '<li><strong>Client:</strong> {{client_name}}</li>'
                . '<li><strong>Date:</strong> {{appointment_date}} at {{start_time}}</li>'
                . '<li><strong>Reason:</strong> {{cancellation_reason}}</li>'
                . '</ul>',
                $variables,
            );
        }
    }

    /**
     * Send reschedule emails to client and staff.
     */
    public function sendReschedule(int $appointmentId, string $oldDate, string $oldTime): void
    {
        $appointment = $this->appointmentRepo->find($appointmentId);

        if ($appointment === null) {
            return;
        }

        $variables = $this->buildVariables($appointment);
        $variables['old_date'] = $oldDate;
        $variables['old_time'] = $oldTime;

        // Client notification.
        $this->sendAndLog(
            $appointmentId,
            'reschedule',
            'client',
            $appointment->client_email,
            'Appointment Rescheduled - BMN Boston',
            '<p>Hi {{client_name}},</p>'
            . '<p>Your appointment has been rescheduled:</p>'
            . '<ul>'
            . '<li><strong>Previous:</strong> {{old_date}} at {{old_time}}</li>'
            . '<li><strong>New Date:</strong> {{appointment_date}}</li>'
            . '<li><strong>New Time:</strong> {{start_time}} - {{end_time}}</li>'
            . '<li><strong>With:</strong> {{staff_name}}</li>'
            . '</ul>',
            $variables,
        );

        // Staff notification.
        if (!empty($appointment->staff_email)) {
            $this->sendAndLog(
                $appointmentId,
                'reschedule',
                'staff',
                $appointment->staff_email,
                'Appointment Rescheduled - ' . $appointment->client_name,
                '<p>An appointment has been rescheduled:</p>'
                . '<ul>'
                . '<li><strong>Client:</strong> {{client_name}}</li>'
                . '<li><strong>Previous:</strong> {{old_date}} at {{old_time}}</li>'
                . '<li><strong>New:</strong> {{appointment_date}} at {{start_time}}</li>'
                . '</ul>',
                $variables,
            );
        }
    }

    /**
     * Process cron-based reminders (24h and 1h).
     */
    public function processReminders(): void
    {
        $this->processReminders24h();
        $this->processReminders1h();
    }

    /**
     * Process 24-hour reminders.
     */
    private function processReminders24h(): void
    {
        $appointments = $this->appointmentRepo->findDueReminders24h();

        foreach ($appointments as $appointment) {
            $attendees = $this->attendeeRepo->findUnsentReminders24h((int) $appointment->id);
            $variables = $this->buildVariables($appointment);

            foreach ($attendees as $attendee) {
                $variables['attendee_name'] = $attendee->name;

                $this->sendAndLog(
                    (int) $appointment->id,
                    'reminder_24h',
                    'client',
                    $attendee->email,
                    'Appointment Tomorrow - BMN Boston',
                    '<p>Hi {{attendee_name}},</p>'
                    . '<p>This is a reminder that you have an appointment tomorrow:</p>'
                    . '<ul>'
                    . '<li><strong>Date:</strong> {{appointment_date}}</li>'
                    . '<li><strong>Time:</strong> {{start_time}}</li>'
                    . '<li><strong>Type:</strong> {{type_name}}</li>'
                    . '<li><strong>With:</strong> {{staff_name}}</li>'
                    . '</ul>',
                    $variables,
                );

                $this->attendeeRepo->markReminderSent((int) $attendee->id, '24h');
            }
        }
    }

    /**
     * Process 1-hour reminders.
     */
    private function processReminders1h(): void
    {
        $appointments = $this->appointmentRepo->findDueReminders1h();

        foreach ($appointments as $appointment) {
            $attendees = $this->attendeeRepo->findUnsentReminders1h((int) $appointment->id);
            $variables = $this->buildVariables($appointment);

            foreach ($attendees as $attendee) {
                $variables['attendee_name'] = $attendee->name;

                $this->sendAndLog(
                    (int) $appointment->id,
                    'reminder_1h',
                    'client',
                    $attendee->email,
                    'Appointment in 1 Hour - BMN Boston',
                    '<p>Hi {{attendee_name}},</p>'
                    . '<p>Your appointment is starting in about 1 hour:</p>'
                    . '<ul>'
                    . '<li><strong>Time:</strong> {{start_time}}</li>'
                    . '<li><strong>Type:</strong> {{type_name}}</li>'
                    . '<li><strong>With:</strong> {{staff_name}}</li>'
                    . '</ul>',
                    $variables,
                );

                $this->attendeeRepo->markReminderSent((int) $attendee->id, '1h');
            }
        }
    }

    /**
     * Build template variables from an appointment record.
     */
    private function buildVariables(object $appointment): array
    {
        return [
            'client_name'      => $appointment->client_name,
            'client_email'     => $appointment->client_email,
            'appointment_date' => $appointment->appointment_date,
            'start_time'       => substr($appointment->start_time, 0, 5),
            'end_time'         => substr($appointment->end_time, 0, 5),
            'type_name'        => $appointment->type_name ?? 'Appointment',
            'staff_name'       => $appointment->staff_name ?? 'Staff',
            'staff_email'      => $appointment->staff_email ?? '',
            'cancel_hours'     => '2',
        ];
    }

    /**
     * Send an email and log the result.
     */
    private function sendAndLog(
        int $appointmentId,
        string $notificationType,
        string $recipientType,
        string $recipientEmail,
        string $subject,
        string $template,
        array $variables,
    ): void {
        $sent = $this->emailService->sendTemplate(
            $recipientEmail,
            $subject,
            $template,
            $variables,
            ['context' => 'appointment']
        );

        $this->logRepo->logNotification(
            $appointmentId,
            $notificationType,
            $recipientType,
            $recipientEmail,
            $subject,
            $sent ? 'sent' : 'failed',
            $sent ? null : 'Email delivery failed',
        );
    }
}

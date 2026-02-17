<?php

declare(strict_types=1);

namespace BMN\Appointments\Api\Controllers;

use BMN\Appointments\Repository\AppointmentTypeRepository;
use BMN\Appointments\Service\AppointmentService;
use BMN\Appointments\Service\AvailabilityService;
use BMN\Appointments\Service\StaffService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for appointment endpoints.
 *
 * 10 routes covering types, staff, availability, CRUD, and policy.
 */
final class AppointmentController extends RestController
{
    protected string $resource = 'appointments';

    private readonly AppointmentService $appointmentService;
    private readonly AvailabilityService $availabilityService;
    private readonly StaffService $staffService;
    private readonly AppointmentTypeRepository $typeRepo;

    public function __construct(
        AppointmentService $appointmentService,
        AvailabilityService $availabilityService,
        StaffService $staffService,
        AppointmentTypeRepository $typeRepo,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->appointmentService = $appointmentService;
        $this->availabilityService = $availabilityService;
        $this->staffService = $staffService;
        $this->typeRepo = $typeRepo;
    }

    protected function getRoutes(): array
    {
        return [
            // Public endpoints.
            [
                'path'     => '/types',
                'method'   => 'GET',
                'callback' => 'listTypes',
                'auth'     => false,
            ],
            [
                'path'     => '/staff',
                'method'   => 'GET',
                'callback' => 'listStaff',
                'auth'     => false,
            ],
            [
                'path'     => '/availability',
                'method'   => 'GET',
                'callback' => 'getAvailability',
                'auth'     => false,
            ],
            [
                'path'     => '',
                'method'   => 'POST',
                'callback' => 'createAppointment',
                'auth'     => false, // Optional JWT enrichment handled in method.
            ],
            [
                'path'     => '/policy',
                'method'   => 'GET',
                'callback' => 'getPolicy',
                'auth'     => false,
            ],
            // Authenticated endpoints.
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'listAppointments',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'GET',
                'callback' => 'getAppointment',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'DELETE',
                'callback' => 'cancelAppointment',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<id>\d+)/reschedule',
                'method'   => 'PATCH',
                'callback' => 'rescheduleAppointment',
                'auth'     => true,
            ],
            [
                'path'     => '/(?P<id>\d+)/reschedule-slots',
                'method'   => 'GET',
                'callback' => 'getRescheduleSlots',
                'auth'     => true,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Public endpoints
    // ------------------------------------------------------------------

    /**
     * GET /appointments/types - List active appointment types.
     */
    public function listTypes(WP_REST_Request $request): WP_REST_Response
    {
        $types = $this->typeRepo->findActive();

        $data = array_map(static fn (object $type): array => [
            'id'                => (int) $type->id,
            'name'              => $type->name,
            'slug'              => $type->slug,
            'description'       => $type->description,
            'duration_minutes'  => (int) $type->duration_minutes,
            'color'             => $type->color,
            'requires_approval' => (bool) $type->requires_approval,
            'requires_login'    => (bool) $type->requires_login,
        ], $types);

        return ApiResponse::success($data);
    }

    /**
     * GET /appointments/staff - List active staff.
     */
    public function listStaff(WP_REST_Request $request): WP_REST_Response
    {
        $typeId = $request->get_param('type_id');
        $typeId = $typeId !== null ? (int) $typeId : null;

        $staff = $this->staffService->getActiveStaff($typeId);

        return ApiResponse::success($staff);
    }

    /**
     * GET /appointments/availability - Get available time slots.
     */
    public function getAvailability(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'start_date' => ['type' => 'string', 'required' => true],
            'end_date'   => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $startDate = (string) $request->get_param('start_date');
        $endDate = (string) $request->get_param('end_date');
        $typeId = $request->get_param('type_id');
        $staffId = $request->get_param('staff_id');

        $slots = $this->availabilityService->getAvailableSlots(
            $startDate,
            $endDate,
            $typeId !== null ? (int) $typeId : null,
            $staffId !== null ? (int) $staffId : null,
        );

        return ApiResponse::success($slots);
    }

    /**
     * POST /appointments - Create an appointment.
     */
    public function createAppointment(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'appointment_type_id' => ['type' => 'integer', 'required' => true],
            'date'                => ['type' => 'string', 'required' => true],
            'time'                => ['type' => 'string', 'required' => true],
            'client_name'         => ['type' => 'string', 'required' => true],
            'client_email'        => ['type' => 'email', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        // Optional JWT enrichment: if user is logged in, attach user_id.
        $user = $this->getCurrentUser();

        $data = [
            'appointment_type_id' => (int) $request->get_param('appointment_type_id'),
            'staff_id'            => $request->get_param('staff_id'),
            'date'                => (string) $request->get_param('date'),
            'time'                => (string) $request->get_param('time'),
            'client_name'         => (string) $request->get_param('client_name'),
            'client_email'        => (string) $request->get_param('client_email'),
            'client_phone'        => $request->get_param('client_phone'),
            'user_id'             => $user?->ID ?? $request->get_param('user_id'),
            'listing_id'          => $request->get_param('listing_id'),
            'notes'               => $request->get_param('notes'),
            'attendees'           => $request->get_param('attendees'),
        ];

        try {
            $appointment = $this->appointmentService->createAppointment($data);
            return ApiResponse::success($appointment, [], 201);
        } catch (RuntimeException $e) {
            $code = match (true) {
                str_contains($e->getMessage(), 'Too many') => 429,
                str_contains($e->getMessage(), 'just booked') => 409,
                default => 400,
            };
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * GET /appointments/policy - Cancellation/reschedule policy.
     */
    public function getPolicy(WP_REST_Request $request): WP_REST_Response
    {
        return ApiResponse::success($this->appointmentService->getPolicy());
    }

    // ------------------------------------------------------------------
    // Authenticated endpoints
    // ------------------------------------------------------------------

    /**
     * GET /appointments - List user's appointments.
     */
    public function listAppointments(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $filters = array_filter([
            'status'    => $request->get_param('status'),
            'from_date' => $request->get_param('from_date'),
            'to_date'   => $request->get_param('to_date'),
        ]);

        $appointments = $this->appointmentService->getUserAppointments((int) $user->ID, $filters);

        return ApiResponse::success($appointments);
    }

    /**
     * GET /appointments/{id} - Appointment detail.
     */
    public function getAppointment(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        try {
            $appointment = $this->appointmentService->getAppointment(
                (int) $request->get_param('id'),
                (int) $user->ID,
            );
            return ApiResponse::success($appointment);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'Not authorized') ? 403 : 404;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * DELETE /appointments/{id} - Cancel appointment.
     */
    public function cancelAppointment(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reason = (string) ($request->get_param('reason') ?? 'Cancelled by user.');

        try {
            $appointment = $this->appointmentService->cancelAppointment(
                (int) $request->get_param('id'),
                (int) $user->ID,
                $reason,
            );
            return ApiResponse::success($appointment);
        } catch (RuntimeException $e) {
            $code = match (true) {
                str_contains($e->getMessage(), 'Not authorized') => 403,
                str_contains($e->getMessage(), 'not found') => 404,
                default => 400,
            };
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * PATCH /appointments/{id}/reschedule - Reschedule appointment.
     */
    public function rescheduleAppointment(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'date' => ['type' => 'string', 'required' => true],
            'time' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $appointment = $this->appointmentService->rescheduleAppointment(
                (int) $request->get_param('id'),
                (int) $user->ID,
                (string) $request->get_param('date'),
                (string) $request->get_param('time'),
            );
            return ApiResponse::success($appointment);
        } catch (RuntimeException $e) {
            $code = match (true) {
                str_contains($e->getMessage(), 'Not authorized') => 403,
                str_contains($e->getMessage(), 'not found') => 404,
                default => 400,
            };
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * GET /appointments/{id}/reschedule-slots - Available reschedule slots.
     */
    public function getRescheduleSlots(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $valid = $this->validateParams($request, [
            'start_date' => ['type' => 'string', 'required' => true],
            'end_date'   => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        try {
            $slots = $this->appointmentService->getRescheduleSlots(
                (int) $request->get_param('id'),
                (int) $user->ID,
                (string) $request->get_param('start_date'),
                (string) $request->get_param('end_date'),
            );
            return ApiResponse::success($slots);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }
}

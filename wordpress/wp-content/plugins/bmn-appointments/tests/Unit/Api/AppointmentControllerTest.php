<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Api;

use BMN\Appointments\Api\Controllers\AppointmentController;
use BMN\Appointments\Repository\AppointmentTypeRepository;
use BMN\Appointments\Service\AppointmentService;
use BMN\Appointments\Service\AvailabilityService;
use BMN\Appointments\Service\StaffService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class AppointmentControllerTest extends TestCase
{
    private AppointmentService $appointmentService;
    private AvailabilityService $availabilityService;
    private StaffService $staffService;
    private AppointmentTypeRepository $typeRepo;
    private AppointmentController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];
        unset($GLOBALS['current_user']);

        $this->appointmentService = $this->createMock(AppointmentService::class);
        $this->availabilityService = $this->createMock(AvailabilityService::class);
        $this->staffService = $this->createMock(StaffService::class);
        $this->typeRepo = $this->createMock(AppointmentTypeRepository::class);

        $this->controller = new AppointmentController(
            $this->appointmentService,
            $this->availabilityService,
            $this->staffService,
            $this->typeRepo,
        );
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public function testRegistersTypesRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/appointments/types', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersStaffRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/appointments/staff', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersAvailabilityRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/appointments/availability', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersCreateRoute(): void
    {
        $this->controller->registerRoutes();
        // POST route registered on empty path.
        $this->assertArrayHasKey('bmn/v1/appointments', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersPolicyRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/appointments/policy', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersRescheduleRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/appointments/(?P<id>\d+)/reschedule', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersRescheduleSlotsRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/appointments/(?P<id>\d+)/reschedule-slots', $GLOBALS['wp_rest_routes']);
    }

    public function testPublicRoutesHaveNoAuth(): void
    {
        $this->controller->registerRoutes();

        $publicRoutes = [
            'appointments/types',
            'appointments/staff',
            'appointments/availability',
            'appointments/policy',
        ];

        foreach ($publicRoutes as $route) {
            $this->assertSame(
                '__return_true',
                $GLOBALS['wp_rest_routes']["bmn/v1/{$route}"]['permission_callback'],
                "Route {$route} should be public"
            );
        }
    }

    // ------------------------------------------------------------------
    // listTypes
    // ------------------------------------------------------------------

    public function testListTypesReturnsActiveTypes(): void
    {
        $this->typeRepo->method('findActive')->willReturn([
            (object) [
                'id' => 1, 'name' => 'Showing', 'slug' => 'showing',
                'description' => 'Property showing', 'duration_minutes' => 30,
                'color' => '#3B82F6', 'requires_approval' => 0, 'requires_login' => 0,
            ],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/appointments/types');
        $response = $this->controller->listTypes($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame('Showing', $data['data'][0]['name']);
    }

    // ------------------------------------------------------------------
    // listStaff
    // ------------------------------------------------------------------

    public function testListStaffReturnsStaff(): void
    {
        $this->staffService->method('getActiveStaff')->willReturn([
            ['id' => 1, 'name' => 'Steve', 'email' => 'steve@test.com', 'phone' => null, 'is_primary' => true],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/appointments/staff');
        $response = $this->controller->listStaff($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
    }

    // ------------------------------------------------------------------
    // getAvailability
    // ------------------------------------------------------------------

    public function testGetAvailabilityReturnsSlots(): void
    {
        $this->availabilityService->method('getAvailableSlots')->willReturn([
            '2026-03-02' => ['09:00', '09:15', '09:30'],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/appointments/availability');
        $request->set_param('start_date', '2026-03-02');
        $request->set_param('end_date', '2026-03-02');

        $response = $this->controller->getAvailability($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('2026-03-02', $data['data']);
    }

    public function testGetAvailabilityReturns422WhenMissingParams(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/appointments/availability');

        $response = $this->controller->getAvailability($request);

        $this->assertSame(422, $response->get_status());
    }

    // ------------------------------------------------------------------
    // createAppointment
    // ------------------------------------------------------------------

    public function testCreateAppointmentReturns201(): void
    {
        $this->appointmentService->method('createAppointment')->willReturn([
            'id' => 1, 'status' => 'confirmed',
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/appointments');
        $request->set_param('appointment_type_id', 1);
        $request->set_param('date', '2026-03-02');
        $request->set_param('time', '10:00');
        $request->set_param('client_name', 'John');
        $request->set_param('client_email', 'john@test.com');

        $response = $this->controller->createAppointment($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testCreateAppointmentReturns422WhenMissingParams(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/appointments');
        $request->set_param('date', '2026-03-02');

        $response = $this->controller->createAppointment($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testCreateAppointmentReturns429OnRateLimit(): void
    {
        $this->appointmentService->method('createAppointment')
            ->willThrowException(new RuntimeException('Too many booking attempts.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/appointments');
        $request->set_param('appointment_type_id', 1);
        $request->set_param('date', '2026-03-02');
        $request->set_param('time', '10:00');
        $request->set_param('client_name', 'John');
        $request->set_param('client_email', 'john@test.com');

        $response = $this->controller->createAppointment($request);

        $this->assertSame(429, $response->get_status());
    }

    public function testCreateAppointmentReturns409OnDoubleBook(): void
    {
        $this->appointmentService->method('createAppointment')
            ->willThrowException(new RuntimeException('This time slot was just booked by someone else.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/appointments');
        $request->set_param('appointment_type_id', 1);
        $request->set_param('date', '2026-03-02');
        $request->set_param('time', '10:00');
        $request->set_param('client_name', 'John');
        $request->set_param('client_email', 'john@test.com');

        $response = $this->controller->createAppointment($request);

        $this->assertSame(409, $response->get_status());
    }

    // ------------------------------------------------------------------
    // getPolicy
    // ------------------------------------------------------------------

    public function testGetPolicyReturnsPolicy(): void
    {
        $this->appointmentService->method('getPolicy')->willReturn([
            'cancel_hours_before' => 2,
            'max_reschedules' => 3,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/appointments/policy');
        $response = $this->controller->getPolicy($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
    }

    // ------------------------------------------------------------------
    // Authenticated endpoints (no user)
    // ------------------------------------------------------------------

    public function testListAppointmentsReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/appointments');
        $response = $this->controller->listAppointments($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testGetAppointmentReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/appointments/1');
        $request->set_param('id', '1');
        $response = $this->controller->getAppointment($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testCancelAppointmentReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('DELETE', '/bmn/v1/appointments/1');
        $request->set_param('id', '1');
        $response = $this->controller->cancelAppointment($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testRescheduleReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('PATCH', '/bmn/v1/appointments/1/reschedule');
        $request->set_param('id', '1');
        $request->set_param('date', '2026-03-03');
        $request->set_param('time', '11:00');
        $response = $this->controller->rescheduleAppointment($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testGetRescheduleSlotsReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/appointments/1/reschedule-slots');
        $request->set_param('id', '1');
        $request->set_param('start_date', '2026-03-01');
        $request->set_param('end_date', '2026-03-07');
        $response = $this->controller->getRescheduleSlots($request);

        $this->assertSame(401, $response->get_status());
    }

    // ------------------------------------------------------------------
    // Authenticated endpoints (with user)
    // ------------------------------------------------------------------

    public function testListAppointmentsSuccessWithUser(): void
    {
        $user = new \WP_User(42);
        $user->user_login = 'john';
        $user->roles = ['subscriber'];
        $GLOBALS['current_user'] = $user;

        $this->appointmentService->method('getUserAppointments')->willReturn([]);

        $request = new WP_REST_Request('GET', '/bmn/v1/appointments');
        $response = $this->controller->listAppointments($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
    }

    public function testCancelAppointmentSuccess(): void
    {
        $user = new \WP_User(42);
        $user->user_login = 'john';
        $user->roles = ['subscriber'];
        $GLOBALS['current_user'] = $user;

        $this->appointmentService->method('cancelAppointment')->willReturn([
            'id' => 1, 'status' => 'cancelled',
        ]);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/appointments/1');
        $request->set_param('id', '1');
        $request->set_param('reason', 'Changed plans');

        $response = $this->controller->cancelAppointment($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
    }

    public function testCancelAppointmentReturns403OnNotAuthorized(): void
    {
        $user = new \WP_User(42);
        $user->user_login = 'john';
        $user->roles = ['subscriber'];
        $GLOBALS['current_user'] = $user;

        $this->appointmentService->method('cancelAppointment')
            ->willThrowException(new RuntimeException('Not authorized to cancel this appointment.'));

        $request = new WP_REST_Request('DELETE', '/bmn/v1/appointments/1');
        $request->set_param('id', '1');

        $response = $this->controller->cancelAppointment($request);

        $this->assertSame(403, $response->get_status());
    }
}

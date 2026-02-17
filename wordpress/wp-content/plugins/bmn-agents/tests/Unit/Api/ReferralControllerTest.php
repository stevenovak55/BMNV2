<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Api;

use BMN\Agents\Api\Controllers\ReferralController;
use BMN\Agents\Service\ReferralService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class ReferralControllerTest extends TestCase
{
    private ReferralService $service;
    private ReferralController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];
        unset($GLOBALS['current_user']);

        $this->service = $this->createMock(ReferralService::class);
        $this->controller = new ReferralController($this->service);
    }

    public function testRegistersReferralRoutes(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agent/referral', $GLOBALS['wp_rest_routes']);
        $this->assertArrayHasKey('bmn/v1/agent/referral/regenerate', $GLOBALS['wp_rest_routes']);
        $this->assertArrayHasKey('bmn/v1/agent/referral/stats', $GLOBALS['wp_rest_routes']);
    }

    public function testGetReferralReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/agent/referral');
        $response = $this->controller->getReferral($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testGetReferralReturnsData(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getAgentReferral')->willReturn([
            'referral_code' => 'ABC123',
            'referral_url'  => 'https://bmnboston.com/register?ref=ABC123',
            'total_signups'  => 5,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agent/referral');
        $response = $this->controller->getReferral($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertSame('ABC123', $data['data']['referral_code']);
    }

    public function testSetReferralCodeReturns422WhenMissing(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/referral');
        $response = $this->controller->setReferralCode($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testSetReferralCodeReturnsSuccess(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('updateCode')->willReturn('NEWCODE');

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/referral');
        $request->set_param('code', 'NEWCODE');
        $response = $this->controller->setReferralCode($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertSame('NEWCODE', $data['data']['referral_code']);
    }

    public function testRegenerateCodeReturnsSuccess(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('generateCode')->willReturn('AUTO1234');

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/referral/regenerate');
        $response = $this->controller->regenerateCode($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertSame('AUTO1234', $data['data']['referral_code']);
    }

    public function testGetStatsReturnsData(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getReferralStats')->willReturn([
            'total_signups' => 20,
            'this_month'    => 5,
            'by_source'     => ['referral_link' => 15, 'organic' => 5],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agent/referral/stats');
        $response = $this->controller->getStats($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertSame(20, $data['data']['total_signups']);
    }

    public function testSetReferralCodeReturns400OnDuplicate(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('updateCode')
            ->willThrowException(new RuntimeException('Referral code already in use.'));

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/referral');
        $request->set_param('code', 'TAKEN');
        $response = $this->controller->setReferralCode($request);

        $this->assertSame(400, $response->get_status());
    }
}

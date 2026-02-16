<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Email;

use BMN\Platform\Email\WpEmailService;
use PHPUnit\Framework\TestCase;

class WpEmailServiceTest extends TestCase
{
    private WpEmailService $service;

    protected function setUp(): void
    {
        $GLOBALS['wp_mail_log'] = [];
        $GLOBALS['wp_options'] = [];

        // Clear any cached config by creating a fresh instance each test.
        $this->service = new WpEmailService();
    }

    // -----------------------------------------------------------------
    //  send()
    // -----------------------------------------------------------------

    public function testSendReturnsTrueOnSuccess(): void
    {
        $result = $this->service->send('user@example.com', 'Test Subject', '<p>Hello</p>');

        $this->assertTrue($result);
    }

    public function testSendLogsToWpMail(): void
    {
        $this->service->send('user@example.com', 'Test Subject', '<p>Body</p>');

        $this->assertCount(1, $GLOBALS['wp_mail_log']);

        $entry = $GLOBALS['wp_mail_log'][0];
        $this->assertSame('user@example.com', $entry['to']);
        $this->assertSame('Test Subject', $entry['subject']);
        $this->assertIsString($entry['message']);
        $this->assertIsArray($entry['headers']);
    }

    public function testSendIncludesHtmlContentType(): void
    {
        $this->service->send('user@example.com', 'Subject', '<p>Body</p>');

        $entry = $GLOBALS['wp_mail_log'][0];
        $headers = $entry['headers'];

        $found = false;
        foreach ($headers as $header) {
            if (str_contains($header, 'Content-Type: text/html')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected Content-Type: text/html header');
    }

    public function testSendUsesDefaultFromAddress(): void
    {
        $this->service->send('user@example.com', 'Subject', '<p>Body</p>');

        $entry = $GLOBALS['wp_mail_log'][0];
        $headers = $entry['headers'];

        $fromFound = false;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'From:') && str_contains($header, 'admin@bmnboston.com')) {
                $fromFound = true;
                break;
            }
        }

        $this->assertTrue($fromFound, 'Expected default From header with admin@bmnboston.com');
    }

    public function testSendUsesCustomFromAddress(): void
    {
        $this->service->send('user@example.com', 'Subject', '<p>Body</p>', [
            'from_email' => 'custom@example.com',
            'from_name'  => 'Custom Sender',
        ]);

        $entry = $GLOBALS['wp_mail_log'][0];
        $headers = $entry['headers'];

        $fromFound = false;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'From:') && str_contains($header, 'custom@example.com') && str_contains($header, 'Custom Sender')) {
                $fromFound = true;
                break;
            }
        }

        $this->assertTrue($fromFound, 'Expected custom From header with custom@example.com');
    }

    public function testSendIncludesCcHeader(): void
    {
        $this->service->send('user@example.com', 'Subject', '<p>Body</p>', [
            'cc' => 'cc@example.com',
        ]);

        $entry = $GLOBALS['wp_mail_log'][0];
        $headers = $entry['headers'];

        $ccFound = false;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Cc:') && str_contains($header, 'cc@example.com')) {
                $ccFound = true;
                break;
            }
        }

        $this->assertTrue($ccFound, 'Expected Cc header with cc@example.com');
    }

    public function testSendIncludesBccHeader(): void
    {
        $this->service->send('user@example.com', 'Subject', '<p>Body</p>', [
            'bcc' => 'bcc@example.com',
        ]);

        $entry = $GLOBALS['wp_mail_log'][0];
        $headers = $entry['headers'];

        $bccFound = false;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Bcc:') && str_contains($header, 'bcc@example.com')) {
                $bccFound = true;
                break;
            }
        }

        $this->assertTrue($bccFound, 'Expected Bcc header with bcc@example.com');
    }

    public function testSendIncludesReplyToHeader(): void
    {
        $this->service->send('user@example.com', 'Subject', '<p>Body</p>', [
            'reply_to' => 'reply@example.com',
        ]);

        $entry = $GLOBALS['wp_mail_log'][0];
        $headers = $entry['headers'];

        $replyToFound = false;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Reply-To:') && str_contains($header, 'reply@example.com')) {
                $replyToFound = true;
                break;
            }
        }

        $this->assertTrue($replyToFound, 'Expected Reply-To header with reply@example.com');
    }

    // -----------------------------------------------------------------
    //  sendTemplate()
    // -----------------------------------------------------------------

    public function testSendTemplateInterpolatesVariables(): void
    {
        $result = $this->service->sendTemplate(
            'user@example.com',
            'Greeting',
            'Hello {{name}}',
            ['name' => 'World']
        );

        $this->assertTrue($result);

        $entry = $GLOBALS['wp_mail_log'][0];
        $this->assertStringContainsString('Hello World', $entry['message']);
    }

    public function testSendTemplatePreservesUnknownPlaceholders(): void
    {
        $this->service->sendTemplate(
            'user@example.com',
            'Greeting',
            'Hello {{unknown}}',
            ['name' => 'World']
        );

        $entry = $GLOBALS['wp_mail_log'][0];
        $this->assertStringContainsString('{{unknown}}', $entry['message']);
    }

    // -----------------------------------------------------------------
    //  getFromHeader()
    // -----------------------------------------------------------------

    public function testGetFromHeaderReturnsDefault(): void
    {
        $header = $this->service->getFromHeader();

        $this->assertStringContainsString('admin@bmnboston.com', $header);
        $this->assertStringContainsString('BMN Boston', $header);
    }

    public function testGetFromHeaderWithAssignedAgent(): void
    {
        // Set up user meta: user 42 has assigned agent 99.
        $GLOBALS['wp_usermeta_42_bmn_assigned_agent_id'] = 99;

        // Set up agent user data.
        $agent = new \stdClass();
        $agent->ID = 99;
        $agent->user_email = 'agent@example.com';
        $agent->first_name = 'Jane';
        $agent->last_name = 'Smith';
        $agent->display_name = 'Jane Smith';
        $GLOBALS['wp_user_id_99'] = $agent;

        $header = $this->service->getFromHeader(42);

        $this->assertStringContainsString('agent@example.com', $header);
        $this->assertStringContainsString('Jane Smith', $header);

        // Clean up.
        unset($GLOBALS['wp_usermeta_42_bmn_assigned_agent_id']);
        unset($GLOBALS['wp_user_id_99']);
    }

    // -----------------------------------------------------------------
    //  buildFooter()
    // -----------------------------------------------------------------

    public function testBuildFooterContainsSiteName(): void
    {
        $footer = $this->service->buildFooter();

        $this->assertStringContainsString('BMN Boston', $footer);
    }

    public function testBuildFooterContainsCopyright(): void
    {
        $footer = $this->service->buildFooter();

        $year = date('Y');
        $this->assertStringContainsString("&copy; {$year}", $footer);
        $this->assertStringContainsString('All rights reserved.', $footer);
    }

    public function testBuildFooterContainsUnsubscribeLink(): void
    {
        $footer = $this->service->buildFooter([
            'unsubscribe_url' => 'https://bmnboston.com/unsubscribe?token=abc123',
        ]);

        $this->assertStringContainsString('Unsubscribe', $footer);
        $this->assertStringContainsString('unsubscribe', $footer);
    }

    public function testBuildFooterPropertyAlertContext(): void
    {
        $footer = $this->service->buildFooter([
            'context' => 'property_alert',
        ]);

        $this->assertStringContainsString('active property alerts', $footer);
    }

    // -----------------------------------------------------------------
    //  HTML wrapper
    // -----------------------------------------------------------------

    public function testSendWrapsBodyInHtml(): void
    {
        $this->service->send('user@example.com', 'Subject', '<p>Body</p>');

        $entry = $GLOBALS['wp_mail_log'][0];
        $message = $entry['message'];

        $this->assertStringStartsWith('<!DOCTYPE html>', $message);
    }
}

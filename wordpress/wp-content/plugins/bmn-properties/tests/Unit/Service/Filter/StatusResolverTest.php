<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Service\Filter;

use BMN\Properties\Service\Filter\StatusResolver;
use PHPUnit\Framework\TestCase;

final class StatusResolverTest extends TestCase
{
    private StatusResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new StatusResolver();
    }

    public function testActiveStatusReturnsCorrectCondition(): void
    {
        $result = $this->resolver->resolve('Active');

        $this->assertStringContainsString("is_archived = 0", $result);
        $this->assertStringContainsString("standard_status = 'Active'", $result);
    }

    public function testPendingStatusReturnsInClause(): void
    {
        $result = $this->resolver->resolve('Pending');

        $this->assertStringContainsString("'Pending'", $result);
        $this->assertStringContainsString("'Active Under Contract'", $result);
    }

    public function testUnderAgreementMapsToSameAsPending(): void
    {
        $pending = $this->resolver->resolve('Pending');
        $underAgreement = $this->resolver->resolve('Under Agreement');

        $this->assertSame($pending, $underAgreement);
    }

    public function testSoldStatusReturnsArchivedClosed(): void
    {
        $result = $this->resolver->resolve('Sold');

        $this->assertStringContainsString("is_archived = 1", $result);
        $this->assertStringContainsString("standard_status = 'Closed'", $result);
    }

    public function testDefaultsToActiveWhenEmpty(): void
    {
        $result = $this->resolver->resolve('');

        $this->assertStringContainsString("is_archived = 0", $result);
        $this->assertStringContainsString("standard_status = 'Active'", $result);
    }

    public function testDefaultsToActiveWhenInvalid(): void
    {
        $result = $this->resolver->resolve('NonExistentStatus');

        $this->assertStringContainsString("is_archived = 0", $result);
    }

    public function testAcceptsCommaSeparatedStatuses(): void
    {
        $result = $this->resolver->resolve('Active, Sold');

        $this->assertStringContainsString('OR', $result);
        $this->assertStringContainsString("is_archived = 0", $result);
        $this->assertStringContainsString("is_archived = 1", $result);
    }

    public function testAcceptsArrayOfStatuses(): void
    {
        $result = $this->resolver->resolve(['Active', 'Pending']);

        $this->assertStringContainsString('OR', $result);
    }

    public function testCaseInsensitive(): void
    {
        $result = $this->resolver->resolve('active');

        $this->assertStringContainsString("is_archived = 0", $result);
        $this->assertStringContainsString("standard_status = 'Active'", $result);
    }

    public function testIncludesArchivedForSold(): void
    {
        $this->assertTrue($this->resolver->includesArchived('Sold'));
        $this->assertFalse($this->resolver->includesArchived('Active'));
        $this->assertFalse($this->resolver->includesArchived('Pending'));
    }
}

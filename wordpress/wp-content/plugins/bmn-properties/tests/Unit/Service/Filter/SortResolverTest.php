<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Service\Filter;

use BMN\Properties\Service\Filter\SortResolver;
use PHPUnit\Framework\TestCase;

final class SortResolverTest extends TestCase
{
    private SortResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SortResolver();
    }

    public function testDefaultSortIsListDateDesc(): void
    {
        $result = $this->resolver->resolve(null);

        $this->assertSame('listing_contract_date DESC', $result);
    }

    public function testEmptyStringReturnsDefault(): void
    {
        $result = $this->resolver->resolve('');

        $this->assertSame('listing_contract_date DESC', $result);
    }

    public function testPriceAsc(): void
    {
        $this->assertSame('list_price ASC', $this->resolver->resolve('price_asc'));
    }

    public function testPriceDesc(): void
    {
        $this->assertSame('list_price DESC', $this->resolver->resolve('price_desc'));
    }

    public function testListDateAsc(): void
    {
        $this->assertSame('listing_contract_date ASC', $this->resolver->resolve('list_date_asc'));
    }

    public function testBedsDesc(): void
    {
        $this->assertSame('bedrooms_total DESC', $this->resolver->resolve('beds_desc'));
    }

    public function testSqftDesc(): void
    {
        $this->assertSame('living_area DESC', $this->resolver->resolve('sqft_desc'));
    }

    public function testDomAscAndDesc(): void
    {
        $this->assertSame('days_on_market ASC', $this->resolver->resolve('dom_asc'));
        $this->assertSame('days_on_market DESC', $this->resolver->resolve('dom_desc'));
    }

    public function testInvalidSortReturnsDefault(): void
    {
        $result = $this->resolver->resolve('invalid_sort');

        $this->assertSame('listing_contract_date DESC', $result);
    }

    public function testCaseInsensitive(): void
    {
        $this->assertSame('list_price ASC', $this->resolver->resolve('PRICE_ASC'));
        $this->assertSame('list_price ASC', $this->resolver->resolve('Price_Asc'));
    }
}

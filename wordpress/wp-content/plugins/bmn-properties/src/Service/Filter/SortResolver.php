<?php

declare(strict_types=1);

namespace BMN\Properties\Service\Filter;

/**
 * Maps user-facing sort parameter to SQL ORDER BY clause.
 *
 * Supported sort values:
 *   price_asc, price_desc, list_date_asc, list_date_desc (default),
 *   beds_desc, sqft_desc, dom_asc, dom_desc
 */
final class SortResolver
{
    /**
     * @var array<string, string>
     */
    private const SORT_MAP = [
        'price_asc'      => 'list_price ASC',
        'price_desc'     => 'list_price DESC',
        'list_date_asc'  => 'listing_contract_date ASC',
        'list_date_desc' => 'listing_contract_date DESC',
        'beds_desc'      => 'bedrooms_total DESC',
        'sqft_desc'      => 'living_area DESC',
        'dom_asc'        => 'days_on_market ASC',
        'dom_desc'       => 'days_on_market DESC',
    ];

    private const DEFAULT_SORT = 'listing_contract_date DESC';

    /**
     * Resolve a sort parameter string to a SQL ORDER BY clause.
     *
     * @param string|null $sort Sort parameter from the request.
     * @return string SQL ORDER BY clause (without the ORDER BY keyword).
     */
    public function resolve(?string $sort): string
    {
        if ($sort === null || $sort === '') {
            return self::DEFAULT_SORT;
        }

        $key = strtolower(trim($sort));

        return self::SORT_MAP[$key] ?? self::DEFAULT_SORT;
    }
}

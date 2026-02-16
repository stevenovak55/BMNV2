<?php

declare(strict_types=1);

namespace BMN\Properties\Service\Filter;

/**
 * Value object returned by FilterBuilder.
 *
 * Encapsulates the generated WHERE clause, ORDER BY clause, and metadata
 * about the query (e.g., whether school post-filtering is needed).
 */
final class FilterResult
{
    public function __construct(
        public readonly string $where,
        public readonly string $orderBy,
        public readonly bool $isDirectLookup,
        public readonly bool $hasSchoolFilters,
        public readonly array $schoolCriteria,
        public readonly int $overfetchMultiplier,
    ) {}
}

<?php

declare(strict_types=1);

namespace BMN\Properties\Service\Filter;

/**
 * Maps user-facing status strings to database conditions.
 *
 * Accepted inputs:
 *   - "Active" (default)      → is_archived = 0 AND standard_status = 'Active'
 *   - "Pending"/"Under Agreement" → standard_status IN ('Pending', 'Active Under Contract')
 *   - "Sold"                  → is_archived = 1 AND standard_status = 'Closed'
 *
 * Accepts a single string, comma-separated string, or array.
 */
final class StatusResolver
{
    /**
     * Mapping of user-facing status labels to SQL conditions.
     *
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'active'          => "is_archived = 0 AND standard_status = 'Active'",
        'pending'         => "standard_status IN ('Pending', 'Active Under Contract')",
        'under agreement' => "standard_status IN ('Pending', 'Active Under Contract')",
        'sold'            => "is_archived = 1 AND standard_status = 'Closed'",
    ];

    /**
     * Resolve one or more status labels into a WHERE clause fragment.
     *
     * @param string|array $status Status label(s). Comma-separated string or array.
     * @return string SQL WHERE fragment (without leading AND/OR).
     */
    public function resolve(string|array $status): string
    {
        $statuses = is_array($status)
            ? $status
            : array_map('trim', explode(',', $status));

        $conditions = [];

        foreach ($statuses as $s) {
            $key = strtolower(trim($s));

            if ($key === '' || !isset(self::STATUS_MAP[$key])) {
                continue;
            }

            $conditions[] = self::STATUS_MAP[$key];
        }

        if ($conditions === []) {
            return self::STATUS_MAP['active'];
        }

        if (count($conditions) === 1) {
            return $conditions[0];
        }

        // Wrap each condition in parens and OR them together.
        $wrapped = array_map(static fn (string $c): string => "({$c})", $conditions);

        return '(' . implode(' OR ', $wrapped) . ')';
    }

    /**
     * Check if the given status includes archived properties.
     */
    public function includesArchived(string|array $status): bool
    {
        $statuses = is_array($status)
            ? $status
            : array_map('trim', explode(',', $status));

        foreach ($statuses as $s) {
            $key = strtolower(trim($s));
            if ($key === 'sold') {
                return true;
            }
        }

        return false;
    }
}

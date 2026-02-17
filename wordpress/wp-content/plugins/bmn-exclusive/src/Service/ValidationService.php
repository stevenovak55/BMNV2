<?php

declare(strict_types=1);

namespace BMN\Exclusive\Service;

/**
 * Pure validation and sanitization logic for exclusive listings.
 *
 * This service has ZERO dependencies (no DB, no WP functions except sanitization).
 * Fully deterministic and unit-testable.
 */
class ValidationService
{
    // ------------------------------------------------------------------
    // Property type enums
    // ------------------------------------------------------------------

    /** @var string[] */
    public const PROPERTY_TYPES = ['Residential', 'Commercial', 'Land', 'Multi-Family', 'Rental'];

    /** @var array<string, string[]> Property sub-types grouped by type. */
    public const PROPERTY_SUB_TYPES = [
        'Residential'  => ['Single Family Residence', 'Condominium', 'Townhouse', 'Apartment', 'Mobile Home', 'Other'],
        'Commercial'   => ['Commercial', 'Other'],
        'Land'         => ['Land', 'Farm', 'Ranch', 'Other'],
        'Multi-Family' => ['Multi Family', 'Other'],
        'Rental'       => ['Single Family Residence', 'Condominium', 'Townhouse', 'Apartment', 'Other'],
    ];

    // ------------------------------------------------------------------
    // Status lifecycle
    // ------------------------------------------------------------------

    /** @var string[] */
    public const STATUSES = ['draft', 'active', 'pending', 'closed', 'withdrawn', 'expired', 'canceled'];

    // ------------------------------------------------------------------
    // Exclusive tag options
    // ------------------------------------------------------------------

    /** @var string[] */
    public const EXCLUSIVE_TAGS = ['Exclusive', 'Coming Soon', 'Off-Market', 'Pocket Listing', 'Pre-Market', 'Private'];

    // ------------------------------------------------------------------
    // Status transitions: from => [allowed to states]
    // ------------------------------------------------------------------

    /** @var array<string, string[]> */
    public const STATUS_TRANSITIONS = [
        'draft'     => ['active', 'canceled'],
        'active'    => ['pending', 'withdrawn', 'expired', 'closed'],
        'pending'   => ['active', 'closed', 'withdrawn'],
        'closed'    => [],
        'withdrawn' => ['active', 'draft'],
        'expired'   => ['active', 'draft'],
        'canceled'  => ['draft'],
    ];

    // ------------------------------------------------------------------
    // Required fields for creation
    // ------------------------------------------------------------------

    private const REQUIRED_FIELDS = [
        'property_type',
        'list_price',
        'street_number',
        'street_name',
        'city',
        'state',
        'postal_code',
    ];

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Validate data for creating a new exclusive listing.
     *
     * @param array<string, mixed> $data The listing data to validate.
     *
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateCreate(array $data): array
    {
        $errors = [];

        // Check required fields.
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        // Validate individual fields (only if present, to collect all errors).
        $errors = array_merge($errors, $this->validateFields($data));

        return [
            'valid'  => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Validate data for updating an existing exclusive listing.
     *
     * Only validates fields that are present (partial update). Nothing is required.
     *
     * @param array<string, mixed> $data The listing data to validate.
     *
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateUpdate(array $data): array
    {
        $errors = $this->validateFields($data);

        return [
            'valid'  => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Check if a status transition is allowed.
     *
     * @param string $from Current status.
     * @param string $to   Desired new status.
     */
    public function validateStatusTransition(string $from, string $to): bool
    {
        if (!isset(self::STATUS_TRANSITIONS[$from])) {
            return false;
        }

        return in_array($to, self::STATUS_TRANSITIONS[$from], true);
    }

    /**
     * Sanitize and normalize listing data.
     *
     * Trims strings, uppercases state, formats postal code, ensures price is float,
     * normalizes bathroom fields, etc.
     *
     * @param array<string, mixed> $data Raw listing data.
     *
     * @return array<string, mixed> Sanitized listing data.
     */
    public function sanitizeListingData(array $data): array
    {
        // Trim all string values.
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        // Uppercase state.
        if (isset($data['state'])) {
            $data['state'] = strtoupper(trim((string) $data['state']));
        }

        // Format postal code: strip spaces, ensure proper format.
        if (isset($data['postal_code'])) {
            $data['postal_code'] = $this->normalizePostalCode((string) $data['postal_code']);
        }

        // Ensure price fields are float.
        $priceFields = ['list_price', 'original_list_price', 'tax_annual_amount', 'association_fee'];
        foreach ($priceFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (float) $data[$field];
            }
        }

        // Ensure integer fields.
        $intFields = [
            'bedrooms_total', 'bathrooms_full', 'bathrooms_half',
            'building_area_total', 'year_built', 'garage_spaces',
            'stories_total', 'parking_total', 'tax_year',
        ];
        foreach ($intFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        // Ensure float fields.
        $floatFields = ['bathrooms_total', 'lot_size_acres', 'latitude', 'longitude'];
        foreach ($floatFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (float) $data[$field];
            }
        }

        // Ensure boolean fields are 0 or 1.
        $boolFields = [
            'has_pool', 'has_fireplace', 'has_basement', 'has_hoa',
            'pet_friendly', 'waterfront_yn', 'view_yn',
        ];
        foreach ($boolFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $data[$field] ? 1 : 0;
            }
        }

        // Normalize bathroom fields.
        $data = $this->normalizeBathrooms($data);

        return $data;
    }

    /**
     * Return the full options structure for front-end consumption.
     *
     * @return array{property_types: string[], property_sub_types: array<string, string[]>,
     *               statuses: string[], exclusive_tags: string[], status_transitions: array<string, string[]>}
     */
    public function getOptions(): array
    {
        return [
            'property_types'     => self::PROPERTY_TYPES,
            'property_sub_types' => self::PROPERTY_SUB_TYPES,
            'statuses'           => self::STATUSES,
            'exclusive_tags'     => self::EXCLUSIVE_TAGS,
            'status_transitions' => self::STATUS_TRANSITIONS,
        ];
    }

    // ------------------------------------------------------------------
    // Private validation helpers
    // ------------------------------------------------------------------

    /**
     * Validate individual fields that are present in the data.
     *
     * @return array<string, string> Field => error message pairs.
     */
    private function validateFields(array $data): array
    {
        $errors = [];

        // Validate property_type.
        if (isset($data['property_type']) && !in_array($data['property_type'], self::PROPERTY_TYPES, true)) {
            $errors['property_type'] = 'Invalid property type. Must be one of: ' . implode(', ', self::PROPERTY_TYPES) . '.';
        }

        // Validate property_sub_type matches the property_type group.
        if (isset($data['property_sub_type']) && $data['property_sub_type'] !== '') {
            $propertyType = $data['property_type'] ?? '';
            if ($propertyType !== '' && isset(self::PROPERTY_SUB_TYPES[$propertyType])) {
                if (!in_array($data['property_sub_type'], self::PROPERTY_SUB_TYPES[$propertyType], true)) {
                    $errors['property_sub_type'] = 'Invalid sub-type for ' . $propertyType . '. Must be one of: '
                        . implode(', ', self::PROPERTY_SUB_TYPES[$propertyType]) . '.';
                }
            } elseif ($propertyType !== '' && !isset(self::PROPERTY_SUB_TYPES[$propertyType])) {
                $errors['property_sub_type'] = 'Cannot validate sub-type: invalid property type.';
            }
        }

        // Validate list_price > 0.
        if (isset($data['list_price'])) {
            $price = (float) $data['list_price'];
            if ($price <= 0) {
                $errors['list_price'] = 'List price must be greater than zero.';
            }
        }

        // Validate state is 2 uppercase letters.
        if (isset($data['state'])) {
            $state = strtoupper(trim((string) $data['state']));
            if (!preg_match('/^[A-Z]{2}$/', $state)) {
                $errors['state'] = 'State must be exactly 2 uppercase letters (e.g., MA).';
            }
        }

        // Validate postal_code is 5 digits or 5+4 format (with optional dash).
        if (isset($data['postal_code'])) {
            $postalCode = trim((string) $data['postal_code']);
            if (!preg_match('/^\d{5}(-?\d{4})?$/', $postalCode)) {
                $errors['postal_code'] = 'Postal code must be 5 digits or 5+4 format (e.g., 02101 or 02101-1234).';
            }
        }

        // Validate status.
        if (isset($data['status']) && !in_array($data['status'], self::STATUSES, true)) {
            $errors['status'] = 'Invalid status. Must be one of: ' . implode(', ', self::STATUSES) . '.';
        }

        // Validate exclusive_tag.
        if (isset($data['exclusive_tag']) && !in_array($data['exclusive_tag'], self::EXCLUSIVE_TAGS, true)) {
            $errors['exclusive_tag'] = 'Invalid exclusive tag. Must be one of: ' . implode(', ', self::EXCLUSIVE_TAGS) . '.';
        }

        return $errors;
    }

    // ------------------------------------------------------------------
    // Private sanitization helpers
    // ------------------------------------------------------------------

    /**
     * Normalize postal code format: strip whitespace, ensure dash for 9-digit codes.
     */
    private function normalizePostalCode(string $postalCode): string
    {
        // Remove all whitespace.
        $postalCode = preg_replace('/\s+/', '', $postalCode);

        // If 9 consecutive digits, insert dash.
        if (preg_match('/^(\d{5})(\d{4})$/', $postalCode, $matches)) {
            return $matches[1] . '-' . $matches[2];
        }

        return $postalCode;
    }

    /**
     * Normalize bathroom fields.
     *
     * If bathrooms_total provided but not full/half, calculate them.
     * If bathrooms_full and bathrooms_half provided but not total, calculate total.
     */
    private function normalizeBathrooms(array $data): array
    {
        $hasTotal = isset($data['bathrooms_total']);
        $hasFull  = isset($data['bathrooms_full']);
        $hasHalf  = isset($data['bathrooms_half']);

        if ($hasTotal && !$hasFull && !$hasHalf) {
            // Calculate full and half from total.
            // Total is stored as decimal (e.g., 2.5 means 2 full + 1 half).
            $total = (float) $data['bathrooms_total'];
            $data['bathrooms_full'] = (int) floor($total);
            $fractional = $total - floor($total);
            $data['bathrooms_half'] = ($fractional >= 0.5) ? (int) round($fractional * 2) : 0;
        } elseif ($hasFull && $hasHalf && !$hasTotal) {
            // Calculate total from full and half.
            $full = (int) $data['bathrooms_full'];
            $half = (int) $data['bathrooms_half'];
            $data['bathrooms_total'] = (float) ($full + ($half * 0.5));
        }

        return $data;
    }
}

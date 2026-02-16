<?php

declare(strict_types=1);

namespace BMN\Extractor\Service;

/**
 * Maps RESO Web API field names to database column names.
 *
 * Single source of truth for all field mappings between the Bridge MLS
 * RESO Web API responses and the denormalized bmn_properties table.
 * The V1 system maintained separate mappings across 5 normalized tables;
 * V2 flattens everything into a single bmn_properties table.
 */
class DataNormalizer
{
    /**
     * Property field map: DB column => RESO API field name.
     *
     * Covers all ~65 columns of the bmn_properties table, consolidated
     * from V1's listings, listing_details, listing_location, and
     * listing_financial tables.
     */
    public const PROPERTY_FIELD_MAP = [
        // Core listing fields (v1: listings table).
        'listing_key'              => 'ListingKey',
        'listing_id'               => 'ListingId',
        'modification_timestamp'   => 'ModificationTimestamp',
        'creation_timestamp'       => 'CreationTimestamp',
        'status_change_timestamp'  => 'StatusChangeTimestamp',
        'close_date'               => 'CloseDate',
        'purchase_contract_date'   => 'PurchaseContractDate',
        'listing_contract_date'    => 'ListingContractDate',
        'original_entry_timestamp' => 'OriginalEntryTimestamp',
        'off_market_date'          => 'OffMarketDate',
        'standard_status'          => 'StandardStatus',
        'mls_status'               => 'MlsStatus',
        'property_type'            => 'PropertyType',
        'property_sub_type'        => 'PropertySubType',
        'list_price'               => 'ListPrice',
        'original_list_price'      => 'OriginalListPrice',
        'close_price'              => 'ClosePrice',
        'public_remarks'           => 'PublicRemarks',
        'showing_instructions'     => 'ShowingInstructions',
        'photo_count'              => 'PhotosCount',
        'virtual_tour_url_unbranded' => 'VirtualTourURLUnbranded',
        'virtual_tour_url_branded' => 'VirtualTourURLBranded',
        'list_agent_mls_id'        => 'ListAgentMlsId',
        'buyer_agent_mls_id'       => 'BuyerAgentMlsId',
        'list_office_mls_id'       => 'ListOfficeMlsId',
        'buyer_office_mls_id'      => 'BuyerOfficeMlsId',

        // Detail fields (v1: listing_details table).
        'bedrooms_total'             => 'BedroomsTotal',
        'bathrooms_total'            => 'BathroomsTotalInteger',
        'bathrooms_full'             => 'BathroomsFull',
        'bathrooms_half'             => 'BathroomsHalf',
        'living_area'                => 'LivingArea',
        'above_grade_finished_area'  => 'AboveGradeFinishedArea',
        'below_grade_finished_area'  => 'BelowGradeFinishedArea',
        'building_area_total'        => 'BuildingAreaTotal',
        'lot_size_acres'             => 'LotSizeAcres',
        'lot_size_square_feet'       => 'LotSizeSquareFeet',
        'year_built'                 => 'YearBuilt',
        'stories_total'              => 'StoriesTotal',
        'garage_spaces'              => 'GarageSpaces',
        'parking_total'              => 'ParkingTotal',
        'fireplaces_total'           => 'FireplacesTotal',
        'rooms_total'                => 'RoomsTotal',

        // Location fields (v1: listing_location table).
        'unparsed_address'       => 'UnparsedAddress',
        'street_number'          => 'StreetNumber',
        'street_name'            => 'StreetName',
        'unit_number'            => 'UnitNumber',
        'city'                   => 'City',
        'state_or_province'      => 'StateOrProvince',
        'postal_code'            => 'PostalCode',
        'county_or_parish'       => 'CountyOrParish',
        'latitude'               => 'Latitude',
        'longitude'              => 'Longitude',
        'subdivision_name'       => 'SubdivisionName',
        'elementary_school'      => 'ElementarySchool',
        'middle_or_junior_school' => 'MiddleOrJuniorSchool',
        'high_school'            => 'HighSchool',
        'school_district'        => 'SchoolDistrict',

        // Financial fields (v1: listing_financial table).
        'tax_annual_amount'        => 'TaxAnnualAmount',
        'tax_year'                 => 'TaxYear',
        'association_yn'           => 'AssociationYN',
        'association_fee'          => 'AssociationFee',
        'association_fee_frequency' => 'AssociationFeeFrequency',
        'mls_area_major'           => 'MLSAreaMajor',
        'mls_area_minor'           => 'MLSAreaMinor',
    ];

    /**
     * Statuses that indicate a listing is no longer active.
     *
     * @var string[]
     */
    public const ARCHIVED_STATUSES = [
        'Closed',
        'Expired',
        'Withdrawn',
        'Canceled',
    ];

    /**
     * Media field map: DB column => RESO API field name.
     */
    public const MEDIA_FIELD_MAP = [
        'media_key'      => 'MediaKey',
        'media_url'      => 'MediaURL',
        'media_category' => 'MediaCategory',
        'order_index'    => 'Order',
    ];

    /**
     * Agent (Member) field map: DB column => RESO API field name.
     */
    public const AGENT_FIELD_MAP = [
        'agent_mls_id'  => 'MemberMlsId',
        'agent_key'     => 'MemberKey',
        'full_name'     => 'MemberFullName',
        'first_name'    => 'MemberFirstName',
        'last_name'     => 'MemberLastName',
        'email'         => 'MemberEmail',
        'phone'         => 'MemberDirectPhone',
        'office_mls_id' => 'OfficeMlsId',
        'state_license' => 'MemberStateLicense',
        'designation'   => 'MemberDesignation',
    ];

    /**
     * Office field map: DB column => RESO API field name.
     */
    public const OFFICE_FIELD_MAP = [
        'office_mls_id'    => 'OfficeMlsId',
        'office_key'       => 'OfficeKey',
        'office_name'      => 'OfficeName',
        'phone'            => 'OfficePhone',
        'address'          => 'OfficeAddress1',
        'city'             => 'OfficeCity',
        'state_or_province' => 'OfficeStateOrProvince',
        'postal_code'      => 'OfficePostalCode',
    ];

    /**
     * Open house field map: DB column => RESO API field name.
     */
    public const OPEN_HOUSE_FIELD_MAP = [
        'open_house_key'        => 'OpenHouseKey',
        'open_house_date'       => 'OpenHouseDate',
        'open_house_start_time' => 'OpenHouseStartTime',
        'open_house_end_time'   => 'OpenHouseEndTime',
        'open_house_type'       => 'OpenHouseType',
        'open_house_remarks'    => 'OpenHouseRemarks',
        'showing_agent_mls_id'  => 'ShowingAgentMlsId',
    ];

    /**
     * Fields to skip when detecting changes between existing and normalized rows.
     *
     * @var string[]
     */
    private const CHANGE_DETECTION_SKIP = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * Normalize a RESO API property listing into a bmn_properties row.
     *
     * Beyond direct field mapping this method also computes:
     * - is_archived:    true when standard_status is in ARCHIVED_STATUSES
     * - main_photo_url: URL of the first photo from the Media array
     * - days_on_market: calendar days from listing_contract_date to close or now
     * - price_per_sqft: list_price divided by living_area
     *
     * @param array $apiListing Raw RESO API listing data.
     * @return array Associative array keyed by DB column names.
     */
    public function normalizeProperty(array $apiListing): array
    {
        $row = $this->mapFields($apiListing, self::PROPERTY_FIELD_MAP);

        // Computed: is_archived.
        $status = $row['standard_status'] ?? '';
        $row['is_archived'] = $this->isArchivedStatus((string) $status) ? 1 : 0;

        // Computed: main_photo_url from Media array.
        $row['main_photo_url'] = $this->extractMainPhotoUrl($apiListing);

        // Computed: days_on_market.
        $row['days_on_market'] = $this->calculateDaysOnMarket(
            $row['listing_contract_date'] ?? null,
            $row['close_date'] ?? null,
        );

        // Computed: price_per_sqft.
        $row['price_per_sqft'] = $this->calculatePricePerSqft(
            $row['list_price'] ?? null,
            $row['living_area'] ?? null,
        );

        return $row;
    }

    /**
     * Normalize a RESO API media record into a bmn_media row.
     *
     * @param array  $apiMedia   Raw RESO API media data.
     * @param string $listingKey The listing key this media belongs to.
     * @return array Associative array keyed by DB column names.
     */
    public function normalizeMedia(array $apiMedia, string $listingKey): array
    {
        $row = $this->mapFields($apiMedia, self::MEDIA_FIELD_MAP);
        $row['listing_key'] = $listingKey;

        return $row;
    }

    /**
     * Normalize a RESO API agent (Member) record.
     *
     * @param array $apiAgent Raw RESO API member data.
     * @return array Associative array keyed by DB column names.
     */
    public function normalizeAgent(array $apiAgent): array
    {
        return $this->mapFields($apiAgent, self::AGENT_FIELD_MAP);
    }

    /**
     * Normalize a RESO API office record.
     *
     * @param array $apiOffice Raw RESO API office data.
     * @return array Associative array keyed by DB column names.
     */
    public function normalizeOffice(array $apiOffice): array
    {
        return $this->mapFields($apiOffice, self::OFFICE_FIELD_MAP);
    }

    /**
     * Normalize a RESO API open house record.
     *
     * @param array  $apiOpenHouse Raw RESO API open house data.
     * @param string $listingKey   The listing key this open house belongs to.
     * @return array Associative array keyed by DB column names.
     */
    public function normalizeOpenHouse(array $apiOpenHouse, string $listingKey): array
    {
        $row = $this->mapFields($apiOpenHouse, self::OPEN_HOUSE_FIELD_MAP);
        $row['listing_key'] = $listingKey;

        return $row;
    }

    /**
     * Detect which fields changed between an existing DB row and a normalized array.
     *
     * Only compares fields that are present in both the existing row and the
     * normalized data. Skips internal columns (id, created_at, updated_at).
     *
     * @param object $existing   Existing DB row as stdClass.
     * @param array  $normalized Normalized array from a normalize*() method.
     * @return array<int, array{field: string, old_value: mixed, new_value: mixed}>
     */
    public function detectChanges(object $existing, array $normalized): array
    {
        $changes = [];

        foreach ($normalized as $field => $newValue) {
            if (in_array($field, self::CHANGE_DETECTION_SKIP, true)) {
                continue;
            }

            if (!property_exists($existing, $field)) {
                continue;
            }

            $oldValue = $existing->{$field};

            // Cast both to string for comparison to handle type mismatches
            // between DB values (often strings) and normalized values.
            if ((string) ($oldValue ?? '') !== (string) ($newValue ?? '')) {
                $changes[] = [
                    'field'     => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if a standard status indicates an archived (off-market) listing.
     *
     * @param string $status The StandardStatus value.
     * @return bool True if the status is in ARCHIVED_STATUSES.
     */
    public function isArchivedStatus(string $status): bool
    {
        return in_array($status, self::ARCHIVED_STATUSES, true);
    }

    /**
     * Generic field mapper.
     *
     * For each entry in $fieldMap (db_column => api_field), extract the
     * api_field value from $apiData and assign it to db_column.
     *
     * Type handling:
     * - Arrays are JSON-encoded.
     * - Booleans are converted to 0/1.
     * - Empty strings are converted to null.
     * - Strings are trimmed.
     *
     * @param array $apiData  Raw API response data.
     * @param array $fieldMap Mapping of db_column => api_field.
     * @return array Associative array keyed by DB column names.
     */
    private function mapFields(array $apiData, array $fieldMap): array
    {
        $row = [];

        foreach ($fieldMap as $dbColumn => $apiField) {
            if (!array_key_exists($apiField, $apiData)) {
                continue;
            }

            $value = $apiData[$apiField];

            // Arrays: JSON-encode for storage.
            if (is_array($value)) {
                $row[$dbColumn] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                continue;
            }

            // Booleans: convert to 0/1 for MySQL.
            if (is_bool($value)) {
                $row[$dbColumn] = $value ? 1 : 0;
                continue;
            }

            // Strings: trim and convert empty to null.
            if (is_string($value)) {
                $trimmed = trim($value);
                $row[$dbColumn] = $trimmed === '' ? null : $trimmed;
                continue;
            }

            // Numeric / null / other: pass through.
            $row[$dbColumn] = $value;
        }

        return $row;
    }

    /**
     * Extract the main photo URL from the listing's Media array.
     *
     * The RESO API returns media as an array of objects within the listing
     * payload. The first photo (lowest Order value) is used as the main image.
     *
     * @param array $apiListing Raw RESO API listing data.
     * @return string|null URL of the primary photo, or null if none found.
     */
    private function extractMainPhotoUrl(array $apiListing): ?string
    {
        $media = $apiListing['Media'] ?? [];

        if (!is_array($media) || $media === []) {
            return null;
        }

        // The first entry in the Media array is typically the primary photo.
        $firstMedia = $media[0] ?? null;

        if (!is_array($firstMedia)) {
            return null;
        }

        $url = $firstMedia['MediaURL'] ?? null;

        return is_string($url) && $url !== '' ? trim($url) : null;
    }

    /**
     * Calculate days on market from listing contract date.
     *
     * For active listings, calculates days from listing_contract_date to now.
     * For closed listings, calculates days from listing_contract_date to close_date.
     * Uses current_time() for WordPress timezone awareness.
     *
     * @param string|null $listingContractDate Listing contract date (Y-m-d or datetime).
     * @param string|null $closeDate           Close date (Y-m-d or datetime), null if active.
     * @return int|null Days on market, or null if listing_contract_date is unavailable.
     */
    private function calculateDaysOnMarket(?string $listingContractDate, ?string $closeDate): ?int
    {
        if ($listingContractDate === null || $listingContractDate === '') {
            return null;
        }

        try {
            $start = new \DateTimeImmutable($listingContractDate);

            if ($closeDate !== null && $closeDate !== '') {
                $end = new \DateTimeImmutable($closeDate);
            } else {
                // Use WordPress timezone-aware "now" when available.
                $timestamp = function_exists('current_time')
                    ? current_time('timestamp')
                    : time();
                $end = (new \DateTimeImmutable())->setTimestamp((int) $timestamp);
            }

            $diff = $start->diff($end);

            return max(0, $diff->days);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Calculate price per square foot.
     *
     * @param float|int|string|null $listPrice  The listing price.
     * @param float|int|string|null $livingArea  The living area in square feet.
     * @return float|null Price per sqft rounded to 2 decimal places, or null.
     */
    private function calculatePricePerSqft(float|int|string|null $listPrice, float|int|string|null $livingArea): ?float
    {
        if ($listPrice === null || $livingArea === null) {
            return null;
        }

        $price = (float) $listPrice;
        $area = (float) $livingArea;

        if ($area <= 0 || $price <= 0) {
            return null;
        }

        return round($price / $area, 2);
    }
}

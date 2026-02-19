<?php

declare(strict_types=1);

namespace BMN\Properties\Repository;

/**
 * Read-only repository for property search and related data.
 *
 * This does NOT extend the platform Repository base class because
 * search queries need custom SELECT, WHERE, and ORDER BY. All queries
 * use $wpdb->prepare() for safety.
 */
class PropertySearchRepository
{
    /**
     * Columns needed for list/search queries (~25 instead of 126).
     *
     * Reduces I/O for paginated results. Use SELECT * only for single-property
     * detail views. Session 24 test 2.12 showed 2.9x penalty from SELECT *.
     */
    public const LIST_COLUMNS = [
        'listing_key',
        'listing_id',
        'unparsed_address',
        'street_number',
        'street_name',
        'unit_number',
        'city',
        'state_or_province',
        'postal_code',
        'list_price',
        'original_list_price',
        'close_price',
        'bedrooms_total',
        'bathrooms_total',
        'bathrooms_full',
        'bathrooms_half',
        'living_area',
        'lot_size_acres',
        'year_built',
        'garage_spaces',
        'property_type',
        'property_sub_type',
        'standard_status',
        'latitude',
        'longitude',
        'listing_contract_date',
        'days_on_market',
        'main_photo_url',
        'is_archived',
    ];

    /**
     * Comma-separated column list for search queries.
     */
    public const LIST_SELECT = 'listing_key, listing_id, unparsed_address, street_number, street_name, unit_number, city, state_or_province, postal_code, list_price, original_list_price, close_price, bedrooms_total, bathrooms_total, bathrooms_full, bathrooms_half, living_area, lot_size_acres, year_built, garage_spaces, property_type, property_sub_type, standard_status, latitude, longitude, listing_contract_date, days_on_market, main_photo_url, is_archived';

    private \wpdb $wpdb;
    private string $propertiesTable;
    private string $mediaTable;
    private string $agentsTable;
    private string $officesTable;
    private string $openHousesTable;
    private string $historyTable;
    private string $roomsTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->propertiesTable = $wpdb->prefix . 'bmn_properties';
        $this->mediaTable = $wpdb->prefix . 'bmn_media';
        $this->agentsTable = $wpdb->prefix . 'bmn_agents';
        $this->officesTable = $wpdb->prefix . 'bmn_offices';
        $this->openHousesTable = $wpdb->prefix . 'bmn_open_houses';
        $this->historyTable = $wpdb->prefix . 'bmn_property_history';
        $this->roomsTable = $wpdb->prefix . 'bmn_rooms';
    }

    // ------------------------------------------------------------------
    // Property search
    // ------------------------------------------------------------------

    /**
     * Search properties with custom SELECT, WHERE, and ORDER BY.
     *
     * @param string $select   Column list (e.g., '*' or specific columns).
     * @param string $where    Prepared WHERE clause (without WHERE keyword).
     * @param string $orderBy  ORDER BY clause (without keyword).
     * @param int    $limit    Max rows.
     * @param int    $offset   Rows to skip.
     *
     * @return object[] Array of property row objects.
     */
    public function searchProperties(string $select, string $where, string $orderBy, int $limit, int $offset): array
    {
        $sql = "SELECT {$select} FROM {$this->propertiesTable} WHERE {$where} ORDER BY {$orderBy}";
        $sql .= $this->wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);

        return $this->wpdb->get_results($sql) ?? [];
    }

    /**
     * Count properties matching a WHERE clause.
     *
     * @param string $where Prepared WHERE clause (without WHERE keyword).
     * @return int Total count.
     */
    public function countProperties(string $where): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->propertiesTable} WHERE {$where}";

        return (int) $this->wpdb->get_var($sql);
    }

    // ------------------------------------------------------------------
    // Single property
    // ------------------------------------------------------------------

    /**
     * Find a property by MLS listing_id (searches active first, then archived).
     *
     * @param string $listingId MLS number (NOT listing_key).
     * @return object|null Property row or null.
     */
    public function findByListingId(string $listingId): ?object
    {
        // Try active first.
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->propertiesTable} WHERE listing_id = %s AND is_archived = 0 LIMIT 1",
                $listingId
            )
        );

        if ($result !== null) {
            return $result;
        }

        // Fall back to archived.
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->propertiesTable} WHERE listing_id = %s LIMIT 1",
                $listingId
            )
        );

        return $result ?: null;
    }

    // ------------------------------------------------------------------
    // Media
    // ------------------------------------------------------------------

    /**
     * Batch-fetch photos for a list of listing keys (first N per listing).
     *
     * @param string[] $listingKeys Array of listing_key values.
     * @param int      $maxPerListing Max photos per listing (default 5).
     *
     * @return array<string, object[]> Keyed by listing_key.
     */
    public function batchFetchMedia(array $listingKeys, int $maxPerListing = 5): array
    {
        if ($listingKeys === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($listingKeys), '%s'));
        $sql = $this->wpdb->prepare(
            "SELECT listing_key, media_url, media_category, order_index
             FROM {$this->mediaTable}
             WHERE listing_key IN ({$placeholders})
             ORDER BY listing_key, order_index ASC",
            ...$listingKeys
        );

        $rows = $this->wpdb->get_results($sql) ?? [];

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->listing_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            if (count($grouped[$key]) < $maxPerListing) {
                $grouped[$key][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * Fetch all media for a single listing.
     *
     * @param string $listingKey The listing_key value.
     * @return object[] Ordered by order_index.
     */
    public function fetchAllMedia(string $listingKey): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT media_url, media_category, order_index
                 FROM {$this->mediaTable}
                 WHERE listing_key = %s
                 ORDER BY order_index ASC",
                $listingKey
            )
        ) ?? [];
    }

    // ------------------------------------------------------------------
    // Agent / Office
    // ------------------------------------------------------------------

    /**
     * Find an agent by their MLS ID.
     */
    public function findAgent(string $agentMlsId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->agentsTable} WHERE agent_mls_id = %s LIMIT 1",
                $agentMlsId
            )
        );

        return $result ?: null;
    }

    /**
     * Find an office by its MLS ID.
     */
    public function findOffice(string $officeMlsId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->officesTable} WHERE office_mls_id = %s LIMIT 1",
                $officeMlsId
            )
        );

        return $result ?: null;
    }

    // ------------------------------------------------------------------
    // Open Houses
    // ------------------------------------------------------------------

    /**
     * Fetch upcoming open houses for a single listing.
     *
     * @param string $listingKey The listing_key value.
     * @return object[] Upcoming open houses sorted by date/time.
     */
    public function fetchUpcomingOpenHouses(string $listingKey): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT open_house_date, open_house_start_time, open_house_end_time,
                        open_house_type, open_house_remarks
                 FROM {$this->openHousesTable}
                 WHERE listing_key = %s AND open_house_date >= CURDATE()
                 ORDER BY open_house_date ASC, open_house_start_time ASC",
                $listingKey
            )
        ) ?? [];
    }

    /**
     * Batch-fetch the next upcoming open house for multiple listings.
     *
     * @param string[] $listingKeys Array of listing_key values.
     * @return array<string, object> Keyed by listing_key (one per listing).
     */
    public function batchFetchNextOpenHouses(array $listingKeys): array
    {
        if ($listingKeys === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($listingKeys), '%s'));
        $sql = $this->wpdb->prepare(
            "SELECT listing_key, open_house_date, open_house_start_time, open_house_end_time
             FROM {$this->openHousesTable}
             WHERE listing_key IN ({$placeholders}) AND open_house_date >= CURDATE()
             ORDER BY open_house_date ASC, open_house_start_time ASC",
            ...$listingKeys
        );

        $rows = $this->wpdb->get_results($sql) ?? [];

        // Keep only the first (soonest) per listing.
        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->listing_key])) {
                $result[$row->listing_key] = $row;
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Property History
    // ------------------------------------------------------------------

    /**
     * Fetch price/status change history for a listing.
     *
     * @param string $listingKey The listing_key value.
     * @return object[] Sorted by changed_at descending.
     */
    public function fetchPropertyHistory(string $listingKey): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT change_type, field_name, old_value, new_value, changed_at
                 FROM {$this->historyTable}
                 WHERE listing_key = %s
                 ORDER BY changed_at DESC",
                $listingKey
            )
        ) ?? [];
    }

    /**
     * Fetch rooms for a property.
     *
     * @param string $listingKey The listing_key value.
     * @return object[] Sorted by room_type ascending.
     */
    public function fetchRooms(string $listingKey): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT room_type, room_level, room_dimensions, room_area, room_description
                 FROM {$this->roomsTable}
                 WHERE listing_key = %s
                 ORDER BY room_type ASC",
                $listingKey
            )
        ) ?? [];
    }

    // ------------------------------------------------------------------
    // Autocomplete
    // ------------------------------------------------------------------

    /**
     * Autocomplete cities matching a term (active listings only).
     *
     * @return object[] Each: {value, count}
     */
    public function autocompleteCities(string $term): array
    {
        $like = '%' . $this->escapeLike($term) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT city AS value, COUNT(*) AS count
                 FROM {$this->propertiesTable}
                 WHERE is_archived = 0 AND standard_status = 'Active' AND city LIKE %s
                 GROUP BY city
                 ORDER BY count DESC
                 LIMIT 5",
                $like
            )
        ) ?? [];
    }

    /**
     * Autocomplete zip codes matching a term (active listings only).
     *
     * @return object[] Each: {value, count}
     */
    public function autocompleteZips(string $term): array
    {
        $like = $this->escapeLike($term) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT postal_code AS value, COUNT(*) AS count
                 FROM {$this->propertiesTable}
                 WHERE is_archived = 0 AND standard_status = 'Active' AND postal_code LIKE %s
                 GROUP BY postal_code
                 ORDER BY count DESC
                 LIMIT 5",
                $like
            )
        ) ?? [];
    }

    /**
     * Autocomplete neighborhoods matching a term (active listings only).
     * Searches: subdivision_name, mls_area_major, mls_area_minor.
     *
     * @return object[] Each: {value, count}
     */
    public function autocompleteNeighborhoods(string $term): array
    {
        $like = '%' . $this->escapeLike($term) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT value, SUM(count) AS count FROM (
                    SELECT subdivision_name AS value, COUNT(*) AS count
                    FROM {$this->propertiesTable}
                    WHERE is_archived = 0 AND standard_status = 'Active' AND subdivision_name LIKE %s
                    GROUP BY subdivision_name
                    UNION ALL
                    SELECT mls_area_major AS value, COUNT(*) AS count
                    FROM {$this->propertiesTable}
                    WHERE is_archived = 0 AND standard_status = 'Active' AND mls_area_major LIKE %s
                    GROUP BY mls_area_major
                    UNION ALL
                    SELECT mls_area_minor AS value, COUNT(*) AS count
                    FROM {$this->propertiesTable}
                    WHERE is_archived = 0 AND standard_status = 'Active' AND mls_area_minor LIKE %s
                    GROUP BY mls_area_minor
                ) AS neighborhoods
                GROUP BY value
                ORDER BY count DESC
                LIMIT 5",
                $like,
                $like,
                $like
            )
        ) ?? [];
    }

    /**
     * Autocomplete street names matching a term (active listings only).
     *
     * @return object[] Each: {value, count}
     */
    public function autocompleteStreetNames(string $term): array
    {
        $like = '%' . $this->escapeLike($term) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT street_name AS value, COUNT(*) AS count
                 FROM {$this->propertiesTable}
                 WHERE is_archived = 0 AND standard_status = 'Active' AND street_name LIKE %s
                 GROUP BY street_name
                 ORDER BY count DESC
                 LIMIT 5",
                $like
            )
        ) ?? [];
    }

    /**
     * Autocomplete addresses matching a term (includes archived).
     *
     * @return object[] Each: {value, listing_id}
     */
    public function autocompleteAddresses(string $term): array
    {
        $like = '%' . $this->escapeLike($term) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT unparsed_address AS value, listing_id
                 FROM {$this->propertiesTable}
                 WHERE unparsed_address LIKE %s
                 ORDER BY is_archived ASC, listing_contract_date DESC
                 LIMIT 5",
                $like
            )
        ) ?? [];
    }

    /**
     * Autocomplete MLS numbers matching a term (includes archived).
     *
     * @return object[] Each: {value}
     */
    public function autocompleteMlsNumbers(string $term): array
    {
        $like = $this->escapeLike($term) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT listing_id AS value
                 FROM {$this->propertiesTable}
                 WHERE listing_id LIKE %s
                 ORDER BY is_archived ASC, listing_contract_date DESC
                 LIMIT 5",
                $like
            )
        ) ?? [];
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * Escape special characters for a LIKE query.
     */
    private function escapeLike(string $value): string
    {
        if (method_exists($this->wpdb, 'esc_like')) {
            return $this->wpdb->esc_like($value);
        }

        return addcslashes($value, '_%\\');
    }
}

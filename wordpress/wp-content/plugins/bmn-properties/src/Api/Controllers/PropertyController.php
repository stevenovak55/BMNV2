<?php

declare(strict_types=1);

namespace BMN\Properties\Api\Controllers;

use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use BMN\Properties\Service\AutocompleteService;
use BMN\Properties\Service\PropertyDetailService;
use BMN\Properties\Service\PropertySearchService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for property search, detail, and autocomplete.
 *
 * Routes:
 *   GET /bmn/v1/properties              - Search with filters, paginated
 *   GET /bmn/v1/properties/autocomplete  - Type-ahead suggestions
 *   GET /bmn/v1/properties/{listing_id}  - Single property detail
 */
final class PropertyController extends RestController
{
    protected string $resource = 'properties';

    private PropertySearchService $searchService;
    private PropertyDetailService $detailService;
    private AutocompleteService $autocompleteService;

    public function __construct(
        PropertySearchService $searchService,
        PropertyDetailService $detailService,
        AutocompleteService $autocompleteService,
    ) {
        parent::__construct(null); // No auth required for property endpoints.

        $this->searchService = $searchService;
        $this->detailService = $detailService;
        $this->autocompleteService = $autocompleteService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'index',
                'auth'     => false,
                'params'   => [
                    'page'     => ['type' => 'integer', 'default' => 1],
                    'per_page' => ['type' => 'integer', 'default' => 25],
                    'sort'     => ['type' => 'string'],
                    'status'   => ['type' => 'string'],
                ],
            ],
            [
                'path'     => '/autocomplete',
                'method'   => 'GET',
                'callback' => 'autocomplete',
                'auth'     => false,
                'params'   => [
                    'term' => ['type' => 'string', 'required' => true],
                ],
            ],
            [
                'path'     => '/(?P<listing_id>[A-Za-z0-9_-]+)',
                'method'   => 'GET',
                'callback' => 'show',
                'auth'     => false,
            ],
        ];
    }

    /**
     * Search properties with filters and pagination.
     */
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $filters = $this->extractFilters($request);
        $page = (int) ($request->get_param('page') ?: 1);
        $perPage = (int) ($request->get_param('per_page') ?: 25);

        $result = $this->searchService->search($filters, $page, $perPage);

        return ApiResponse::paginated(
            $result['data'],
            $result['total'],
            $result['page'],
            $result['per_page'],
        );
    }

    /**
     * Get a single property by listing_id (MLS number).
     */
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $listingId = $request->get_param('listing_id');

        if (empty($listingId)) {
            return ApiResponse::error('Listing ID is required.', 400);
        }

        $detail = $this->detailService->getByListingId($listingId);

        if ($detail === null) {
            return ApiResponse::error('Property not found.', 404);
        }

        return ApiResponse::success($detail);
    }

    /**
     * Get autocomplete suggestions.
     */
    public function autocomplete(WP_REST_Request $request): WP_REST_Response
    {
        $term = $request->get_param('term') ?? '';

        $suggestions = $this->autocompleteService->suggest($term);

        return ApiResponse::success($suggestions);
    }

    /**
     * Extract filter parameters from the request.
     *
     * @return array<string, mixed>
     */
    private function extractFilters(WP_REST_Request $request): array
    {
        $filterKeys = [
            // Direct lookup.
            'mls_number', 'address',
            // Status.
            'status',
            // Location.
            'city', 'zip', 'neighborhood', 'street_name',
            // Geo.
            'bounds', 'polygon',
            // Type.
            'property_type', 'property_sub_type',
            // Price.
            'min_price', 'max_price', 'price_reduced',
            // Rooms.
            'beds', 'baths',
            // Size.
            'sqft_min', 'sqft_max', 'lot_size_min', 'lot_size_max',
            // Time.
            'year_built_min', 'year_built_max', 'max_dom', 'min_dom', 'new_listing_days',
            // Parking.
            'garage_spaces_min', 'parking_total_min',
            // Amenity.
            'has_virtual_tour', 'has_garage', 'has_fireplace',
            // Special.
            'open_house_only', 'exclusive_only',
            // School (detected for post-filter).
            'school_grade', 'school_district', 'elementary_school', 'middle_school', 'high_school',
            // Sort.
            'sort',
        ];

        $filters = [];

        foreach ($filterKeys as $key) {
            $value = $request->get_param($key);

            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}

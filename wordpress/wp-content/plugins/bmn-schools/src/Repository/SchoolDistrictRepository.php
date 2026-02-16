<?php

declare(strict_types=1);

namespace BMN\Schools\Repository;

use BMN\Platform\Database\Repository;
use BMN\Platform\Geocoding\GeocodingService;

/**
 * Repository for the bmn_school_districts table.
 */
class SchoolDistrictRepository extends Repository
{
    private readonly GeocodingService $geocoding;

    /**
     * Regional school mapping for cross-district assignments.
     * Maps cities to the district that serves them when they don't have their own.
     *
     * @var array<string, string>
     */
    private const REGIONAL_MAPPINGS = [
        'Nahant' => 'Swampscott',
        'Hull' => 'Hingham',
        'Cohasset' => 'Hingham',
        'Winthrop' => 'Revere',
        'Manchester' => 'Essex',
        'Essex' => 'Manchester Essex Regional',
        'Rockport' => 'Rockport',
        'Middleton' => 'Masconomet Regional',
        'Boxford' => 'Masconomet Regional',
        'Topsfield' => 'Masconomet Regional',
        'Wenham' => 'Hamilton-Wenham Regional',
        'Hamilton' => 'Hamilton-Wenham Regional',
        'Hanover' => 'Hanover',
        'Norwell' => 'Norwell',
        'Scituate' => 'Scituate',
        'Marshfield' => 'Marshfield',
        'Duxbury' => 'Duxbury',
        'Pembroke' => 'Pembroke',
        'Hingham' => 'Hingham',
        'Weymouth' => 'Weymouth',
        'Braintree' => 'Braintree',
        'Quincy' => 'Quincy',
        'Milton' => 'Milton',
        'Dedham' => 'Dedham',
        'Needham' => 'Needham',
        'Wellesley' => 'Wellesley',
        'Natick' => 'Natick',
        'Framingham' => 'Framingham',
        'Sudbury' => 'Lincoln-Sudbury Regional',
        'Lincoln' => 'Lincoln-Sudbury Regional',
        'Concord' => 'Concord-Carlisle Regional',
        'Carlisle' => 'Concord-Carlisle Regional',
        'Bedford' => 'Bedford',
        'Burlington' => 'Burlington',
        'Woburn' => 'Woburn',
        'Reading' => 'Reading',
        'Wakefield' => 'Wakefield',
        'Stoneham' => 'Stoneham',
        'Melrose' => 'Melrose',
        'Malden' => 'Malden',
        'Medford' => 'Medford',
        'Somerville' => 'Somerville',
        'Cambridge' => 'Cambridge',
        'Arlington' => 'Arlington',
        'Lexington' => 'Lexington',
        'Winchester' => 'Winchester',
        'Belmont' => 'Belmont',
        'Watertown' => 'Watertown',
        'Waltham' => 'Waltham',
        'Newton' => 'Newton',
        'Brookline' => 'Brookline',
        'Boston' => 'Boston',
    ];

    public function __construct(\wpdb $wpdb, GeocodingService $geocoding)
    {
        parent::__construct($wpdb);
        $this->geocoding = $geocoding;
    }

    protected function getTableName(): string
    {
        return 'bmn_school_districts';
    }

    /**
     * Find a district by NCES ID.
     */
    public function findByNcesId(string $ncesId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE nces_district_id = %s LIMIT 1",
                $ncesId
            )
        );

        return $result ?: null;
    }

    /**
     * Find a district by city name.
     */
    public function findByCity(string $city): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE city = %s LIMIT 1",
                $city
            )
        );

        return $result ?: null;
    }

    /**
     * Find the district that contains a geographic point using boundary GeoJSON.
     */
    public function findForPoint(float $lat, float $lng): ?object
    {
        // Get all districts that have boundary data.
        $districts = $this->wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE boundary_geojson IS NOT NULL"
        );

        foreach ($districts as $district) {
            $geojson = json_decode($district->boundary_geojson, true);
            if ($geojson === null) {
                continue;
            }

            $coordinates = $geojson['coordinates'][0] ?? [];
            if ($coordinates === []) {
                continue;
            }

            // Convert GeoJSON [lng, lat] to [lat, lng] for isPointInPolygon.
            $polygon = array_map(
                static fn (array $point): array => [$point[1], $point[0]],
                $coordinates
            );

            if ($this->geocoding->isPointInPolygon($lat, $lng, $polygon)) {
                return $district;
            }
        }

        return null;
    }

    /**
     * Get the regional school mapping for a city.
     * Returns district name that serves the city, or null if no mapping exists.
     */
    public function getRegionalMapping(string $city): ?string
    {
        return self::REGIONAL_MAPPINGS[$city] ?? null;
    }

    /**
     * Find a district by name.
     */
    public function findByName(string $name): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE name = %s LIMIT 1",
                $name
            )
        );

        return $result ?: null;
    }

    /**
     * Find districts by county.
     *
     * @return object[]
     */
    public function findByCounty(string $county): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE county = %s ORDER BY name ASC",
                $county
            )
        );
    }
}

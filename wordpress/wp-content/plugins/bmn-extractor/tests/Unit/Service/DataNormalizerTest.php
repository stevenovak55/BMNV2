<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Service;

use BMN\Extractor\Service\DataNormalizer;
use PHPUnit\Framework\TestCase;

class DataNormalizerTest extends TestCase
{
    private DataNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DataNormalizer();
    }

    // ------------------------------------------------------------------
    // normalizeProperty — field mapping
    // ------------------------------------------------------------------

    public function testNormalizePropertyMapsCoreLisingFields(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('abc123', $row['listing_key']);
        $this->assertSame('12345678', $row['listing_id']);
        $this->assertSame('Active', $row['standard_status']);
        $this->assertSame('Active', $row['mls_status']);
        $this->assertSame('Residential', $row['property_type']);
        $this->assertSame('Single Family Residence', $row['property_sub_type']);
    }

    public function testNormalizePropertyMapsDetailFields(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame(3, $row['bedrooms_total']);
        $this->assertSame(2, $row['bathrooms_total']);
        $this->assertSame(1, $row['bathrooms_full']);
        $this->assertSame(1, $row['bathrooms_half']);
        $this->assertEquals(1500.00, $row['living_area']);
        $this->assertSame(2000, $row['year_built']);
    }

    public function testNormalizePropertyMapsLocationFields(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('123 Main St', $row['unparsed_address']);
        $this->assertSame('123', $row['street_number']);
        $this->assertSame('Main St', $row['street_name']);
        $this->assertSame('Boston', $row['city']);
        $this->assertSame('MA', $row['state_or_province']);
        $this->assertSame('02101', $row['postal_code']);
        $this->assertEquals(42.3601, $row['latitude']);
        $this->assertEquals(-71.0589, $row['longitude']);
    }

    public function testNormalizePropertyMapsFinancialFields(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertEquals(5000.00, $row['tax_annual_amount']);
        $this->assertSame(2025, $row['tax_year']);
        $this->assertSame(1, $row['association_yn']);
        $this->assertEquals(350.00, $row['association_fee']);
        $this->assertSame('Monthly', $row['association_fee_frequency']);
    }

    public function testNormalizePropertyMapsMlsAreaFields(): void
    {
        $api = $this->buildFullApiListing();
        $api['MLSAreaMajor'] = 'Greater Boston';
        $api['MLSAreaMinor'] = 'Back Bay';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('Greater Boston', $row['mls_area_major']);
        $this->assertSame('Back Bay', $row['mls_area_minor']);
    }

    public function testNormalizePropertyMapsAgentAndOfficeIds(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('AGENT001', $row['list_agent_mls_id']);
        $this->assertSame('AGENT002', $row['buyer_agent_mls_id']);
        $this->assertSame('OFFICE001', $row['list_office_mls_id']);
        $this->assertSame('OFFICE002', $row['buyer_office_mls_id']);
    }

    public function testNormalizePropertyMapsPriceFields(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertEquals(500000, $row['list_price']);
        $this->assertEquals(525000, $row['original_list_price']);
        $this->assertNull($row['close_price']);
    }

    public function testNormalizePropertyMapsTimestampFields(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('2026-01-15T10:30:00Z', $row['modification_timestamp']);
        $this->assertSame('2026-01-01T00:00:00Z', $row['creation_timestamp']);
    }

    // ------------------------------------------------------------------
    // normalizeProperty — computed fields
    // ------------------------------------------------------------------

    public function testNormalizePropertyComputesIsArchivedForActiveStatus(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame(0, $row['is_archived']);
    }

    public function testNormalizePropertyComputesIsArchivedForClosedStatus(): void
    {
        $api = $this->buildFullApiListing();
        $api['StandardStatus'] = 'Closed';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame(1, $row['is_archived']);
    }

    public function testNormalizePropertyComputesMainPhotoUrl(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('https://photos.example.com/photo1.jpg', $row['main_photo_url']);
    }

    public function testNormalizePropertyMainPhotoUrlNullWhenNoMedia(): void
    {
        $api = $this->buildFullApiListing();
        unset($api['Media']);
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertNull($row['main_photo_url']);
    }

    public function testNormalizePropertyComputesDaysOnMarket(): void
    {
        $api = $this->buildFullApiListing();
        $api['ListingContractDate'] = '2026-01-01';
        $api['CloseDate'] = '2026-02-01';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame(31, $row['days_on_market']);
    }

    public function testNormalizePropertyDaysOnMarketNullWithoutContractDate(): void
    {
        $api = $this->buildFullApiListing();
        unset($api['ListingContractDate']);
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertNull($row['days_on_market']);
    }

    public function testNormalizePropertyComputesPricePerSqft(): void
    {
        $api = $this->buildFullApiListing();
        $api['ListPrice'] = 300000;
        $api['LivingArea'] = 1500;
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertEquals(200.00, $row['price_per_sqft']);
    }

    public function testNormalizePropertyPricePerSqftNullWhenZeroArea(): void
    {
        $api = $this->buildFullApiListing();
        $api['LivingArea'] = 0;
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertNull($row['price_per_sqft']);
    }

    public function testNormalizePropertyPricePerSqftNullWhenMissingPrice(): void
    {
        $api = $this->buildFullApiListing();
        unset($api['ListPrice']);
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertNull($row['price_per_sqft']);
    }

    // ------------------------------------------------------------------
    // normalizeProperty — new detail fields
    // ------------------------------------------------------------------

    public function testNormalizePropertyMapsBooleanFlags(): void
    {
        $api = $this->buildFullApiListing();
        $api['PoolPrivateYN'] = true;
        $api['WaterfrontYN'] = false;
        $api['ViewYN'] = true;
        $api['CoolingYN'] = true;
        $api['HeatingYN'] = false;
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame(1, $row['pool_private_yn']);
        $this->assertSame(0, $row['waterfront_yn']);
        $this->assertSame(1, $row['view_yn']);
        $this->assertSame(1, $row['cooling_yn']);
        $this->assertSame(0, $row['heating_yn']);
    }

    public function testNormalizePropertyMapsDetailTextFields(): void
    {
        $api = $this->buildFullApiListing();
        $api['Basement'] = ['Full', 'Finished'];
        $api['Heating'] = ['Forced Air', 'Natural Gas'];
        $api['Roof'] = 'Asphalt Shingle';
        $api['ArchitecturalStyle'] = 'Colonial';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('["Full","Finished"]', $row['basement']);
        $this->assertSame('["Forced Air","Natural Gas"]', $row['heating']);
        $this->assertSame('Asphalt Shingle', $row['roof']);
        $this->assertSame('Colonial', $row['architectural_style']);
    }

    public function testNormalizePropertyMapsExtendedFinancialFields(): void
    {
        $api = $this->buildFullApiListing();
        $api['TaxAssessedValue'] = 450000.00;
        $api['Zoning'] = 'R-1';
        $api['ParcelNumber'] = '123-456-789';
        $api['NumberOfUnitsTotal'] = 4;
        $api['BuyerAgencyCompensation'] = '2.5%';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertEquals(450000.00, $row['tax_assessed_value']);
        $this->assertSame('R-1', $row['zoning']);
        $this->assertSame('123-456-789', $row['parcel_number']);
        $this->assertSame(4, $row['number_of_units_total']);
        $this->assertSame('2.5%', $row['buyer_agency_compensation']);
    }

    public function testNormalizePropertyMapsExtendedLocationFields(): void
    {
        $api = $this->buildFullApiListing();
        $api['StreetDirPrefix'] = 'N';
        $api['StreetDirSuffix'] = 'W';
        $api['BuildingName'] = 'The Residences';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('N', $row['street_dir_prefix']);
        $this->assertSame('W', $row['street_dir_suffix']);
        $this->assertSame('The Residences', $row['building_name']);
    }

    public function testNormalizePropertyMapsExtendedListingFields(): void
    {
        $api = $this->buildFullApiListing();
        $api['ExpirationDate'] = '2026-12-31';
        $api['Contingency'] = 'Inspection';
        $api['PrivateRemarks'] = 'Seller motivated.';
        $api['StructureType'] = 'Detached';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('2026-12-31', $row['expiration_date']);
        $this->assertSame('Inspection', $row['contingency']);
        $this->assertSame('Seller motivated.', $row['private_remarks']);
        $this->assertSame('Detached', $row['structure_type']);
    }

    public function testNormalizePropertyPopulatesExtraDataJson(): void
    {
        $api = $this->buildFullApiListing();
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertArrayHasKey('extra_data', $row);
        $decoded = json_decode($row['extra_data'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('abc123', $decoded['ListingKey']);
        $this->assertSame('12345678', $decoded['ListingId']);
    }

    public function testNormalizePropertyExtraDataSkippedInChangeDetection(): void
    {
        $existing = (object) ['extra_data' => '{}', 'city' => 'Boston'];
        $normalized = ['extra_data' => '{"foo":"bar"}', 'city' => 'Boston'];

        $changes = $this->normalizer->detectChanges($existing, $normalized);
        $fields = array_column($changes, 'field');
        $this->assertNotContains('extra_data', $fields);
    }

    // ------------------------------------------------------------------
    // normalizeProperty — type handling
    // ------------------------------------------------------------------

    public function testBooleanConvertedToInt(): void
    {
        $api = $this->buildFullApiListing();
        $api['AssociationYN'] = true;
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame(1, $row['association_yn']);
    }

    public function testBooleanFalseConvertedToZero(): void
    {
        $api = $this->buildFullApiListing();
        $api['AssociationYN'] = false;
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame(0, $row['association_yn']);
    }

    public function testEmptyStringsConvertedToNull(): void
    {
        $api = $this->buildFullApiListing();
        $api['SubdivisionName'] = '';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertNull($row['subdivision_name']);
    }

    public function testStringsTrimmed(): void
    {
        $api = $this->buildFullApiListing();
        $api['City'] = '  Boston  ';
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('Boston', $row['city']);
    }

    public function testArraysJsonEncoded(): void
    {
        // Use an array value for a field that normally receives arrays.
        // Since PROPERTY_FIELD_MAP doesn't map array fields, test via Media/normalizeMedia.
        $media = ['MediaURL' => 'https://example.com/photo.jpg', 'MediaKey' => 'MK1'];
        $row = $this->normalizer->normalizeMedia($media, 'LK1');

        $this->assertSame('https://example.com/photo.jpg', $row['media_url']);
    }

    public function testMissingFieldsSkipped(): void
    {
        $api = ['ListingKey' => 'test123', 'ListingId' => 'MLS123', 'StandardStatus' => 'Active'];
        $row = $this->normalizer->normalizeProperty($api);

        $this->assertSame('test123', $row['listing_key']);
        $this->assertArrayNotHasKey('city', $row);
        $this->assertArrayNotHasKey('bedrooms_total', $row);
    }

    // ------------------------------------------------------------------
    // normalizeMedia
    // ------------------------------------------------------------------

    public function testNormalizeMediaMapsFields(): void
    {
        $api = [
            'MediaKey' => 'MK1',
            'MediaURL' => 'https://photos.example.com/1.jpg',
            'MediaCategory' => 'Photo',
            'Order' => 1,
        ];
        $row = $this->normalizer->normalizeMedia($api, 'LK123');

        $this->assertSame('MK1', $row['media_key']);
        $this->assertSame('https://photos.example.com/1.jpg', $row['media_url']);
        $this->assertSame('Photo', $row['media_category']);
        $this->assertSame(1, $row['order_index']);
        $this->assertSame('LK123', $row['listing_key']);
    }

    // ------------------------------------------------------------------
    // normalizeAgent
    // ------------------------------------------------------------------

    public function testNormalizeAgentMapsFields(): void
    {
        $api = [
            'MemberMlsId' => 'AGENT001',
            'MemberKey' => 'KEY001',
            'MemberFullName' => 'John Doe',
            'MemberFirstName' => 'John',
            'MemberLastName' => 'Doe',
            'MemberEmail' => 'john@example.com',
            'MemberDirectPhone' => '555-1234',
            'OfficeMlsId' => 'OFFICE001',
        ];
        $row = $this->normalizer->normalizeAgent($api);

        $this->assertSame('AGENT001', $row['agent_mls_id']);
        $this->assertSame('John Doe', $row['full_name']);
        $this->assertSame('john@example.com', $row['email']);
    }

    // ------------------------------------------------------------------
    // normalizeOffice
    // ------------------------------------------------------------------

    public function testNormalizeOfficeMapsFields(): void
    {
        $api = [
            'OfficeMlsId' => 'OFF001',
            'OfficeKey' => 'OFFKEY1',
            'OfficeName' => 'Best Realty',
            'OfficePhone' => '555-9999',
            'OfficeCity' => 'Boston',
        ];
        $row = $this->normalizer->normalizeOffice($api);

        $this->assertSame('OFF001', $row['office_mls_id']);
        $this->assertSame('Best Realty', $row['office_name']);
        $this->assertSame('Boston', $row['city']);
    }

    // ------------------------------------------------------------------
    // normalizeOpenHouse
    // ------------------------------------------------------------------

    public function testNormalizeOpenHouseMapsFields(): void
    {
        $api = [
            'OpenHouseKey' => 'OH1',
            'OpenHouseDate' => '2026-03-01',
            'OpenHouseStartTime' => '10:00:00',
            'OpenHouseEndTime' => '12:00:00',
            'OpenHouseType' => 'Public',
            'OpenHouseRemarks' => 'Welcome!',
            'ShowingAgentMlsId' => 'AGENT001',
        ];
        $row = $this->normalizer->normalizeOpenHouse($api, 'LK999');

        $this->assertSame('OH1', $row['open_house_key']);
        $this->assertSame('2026-03-01', $row['open_house_date']);
        $this->assertSame('Public', $row['open_house_type']);
        $this->assertSame('LK999', $row['listing_key']);
    }

    // ------------------------------------------------------------------
    // isArchivedStatus
    // ------------------------------------------------------------------

    public function testIsArchivedStatusForAllArchivedStatuses(): void
    {
        foreach (['Closed', 'Expired', 'Withdrawn', 'Canceled'] as $status) {
            $this->assertTrue(
                $this->normalizer->isArchivedStatus($status),
                "Expected '{$status}' to be archived."
            );
        }
    }

    public function testIsArchivedStatusFalseForActiveStatuses(): void
    {
        foreach (['Active', 'Pending', 'Active Under Contract', 'Coming Soon'] as $status) {
            $this->assertFalse(
                $this->normalizer->isArchivedStatus($status),
                "Expected '{$status}' to NOT be archived."
            );
        }
    }

    // ------------------------------------------------------------------
    // detectChanges
    // ------------------------------------------------------------------

    public function testDetectChangesFindsChangedFields(): void
    {
        $existing = (object) [
            'listing_key' => 'abc123',
            'list_price' => '500000.00',
            'city' => 'Boston',
            'standard_status' => 'Active',
        ];
        $normalized = [
            'listing_key' => 'abc123',
            'list_price' => 525000,
            'city' => 'Boston',
            'standard_status' => 'Pending',
        ];

        $changes = $this->normalizer->detectChanges($existing, $normalized);

        $this->assertCount(2, $changes);

        $fields = array_column($changes, 'field');
        $this->assertContains('list_price', $fields);
        $this->assertContains('standard_status', $fields);
    }

    public function testDetectChangesSkipsIdAndTimestamps(): void
    {
        $existing = (object) ['id' => 1, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01', 'city' => 'Boston'];
        $normalized = ['id' => 999, 'created_at' => '2026-02-01', 'updated_at' => '2026-02-01', 'city' => 'Boston'];

        $changes = $this->normalizer->detectChanges($existing, $normalized);
        $this->assertCount(0, $changes);
    }

    public function testDetectChangesHandlesNullValues(): void
    {
        $existing = (object) ['city' => null];
        $normalized = ['city' => 'Boston'];

        $changes = $this->normalizer->detectChanges($existing, $normalized);
        $this->assertCount(1, $changes);
        $this->assertSame('city', $changes[0]['field']);
        $this->assertNull($changes[0]['old_value']);
        $this->assertSame('Boston', $changes[0]['new_value']);
    }

    public function testDetectChangesReturnsEmptyWhenNoChanges(): void
    {
        $existing = (object) ['city' => 'Boston', 'list_price' => '500000'];
        $normalized = ['city' => 'Boston', 'list_price' => 500000];

        $changes = $this->normalizer->detectChanges($existing, $normalized);
        $this->assertCount(0, $changes);
    }

    public function testDetectChangesSkipsFieldsNotInExisting(): void
    {
        $existing = (object) ['city' => 'Boston'];
        $normalized = ['city' => 'Boston', 'subdivision_name' => 'Back Bay'];

        $changes = $this->normalizer->detectChanges($existing, $normalized);
        $this->assertCount(0, $changes);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buildFullApiListing(): array
    {
        return [
            'ListingKey' => 'abc123',
            'ListingId' => '12345678',
            'ModificationTimestamp' => '2026-01-15T10:30:00Z',
            'CreationTimestamp' => '2026-01-01T00:00:00Z',
            'StatusChangeTimestamp' => '2026-01-10T08:00:00Z',
            'CloseDate' => null,
            'PurchaseContractDate' => null,
            'ListingContractDate' => '2026-01-01',
            'OriginalEntryTimestamp' => '2026-01-01T00:00:00Z',
            'OffMarketDate' => null,
            'StandardStatus' => 'Active',
            'MlsStatus' => 'Active',
            'PropertyType' => 'Residential',
            'PropertySubType' => 'Single Family Residence',
            'ListPrice' => 500000,
            'OriginalListPrice' => 525000,
            'ClosePrice' => null,
            'PublicRemarks' => 'Beautiful home in Boston.',
            'ShowingInstructions' => 'Call agent.',
            'PhotosCount' => 10,
            'VirtualTourURLUnbranded' => 'https://tour.example.com/unbranded',
            'VirtualTourURLBranded' => 'https://tour.example.com/branded',
            'ListAgentMlsId' => 'AGENT001',
            'BuyerAgentMlsId' => 'AGENT002',
            'ListOfficeMlsId' => 'OFFICE001',
            'BuyerOfficeMlsId' => 'OFFICE002',
            'BedroomsTotal' => 3,
            'BathroomsTotalInteger' => 2,
            'BathroomsFull' => 1,
            'BathroomsHalf' => 1,
            'LivingArea' => 1500.00,
            'AboveGradeFinishedArea' => 1200.00,
            'BelowGradeFinishedArea' => 300.00,
            'BuildingAreaTotal' => 1800.00,
            'LotSizeAcres' => 0.25,
            'LotSizeSquareFeet' => 10890.00,
            'YearBuilt' => 2000,
            'StoriesTotal' => 2,
            'GarageSpaces' => 2,
            'ParkingTotal' => 4,
            'FireplacesTotal' => 1,
            'RoomsTotal' => 8,
            'UnparsedAddress' => '123 Main St',
            'StreetNumber' => '123',
            'StreetName' => 'Main St',
            'UnitNumber' => null,
            'City' => 'Boston',
            'StateOrProvince' => 'MA',
            'PostalCode' => '02101',
            'CountyOrParish' => 'Suffolk',
            'Latitude' => 42.3601,
            'Longitude' => -71.0589,
            'SubdivisionName' => 'Downtown',
            'ElementarySchool' => 'Eliot K-8',
            'MiddleOrJuniorSchool' => 'Eliot K-8',
            'HighSchool' => 'Boston Latin',
            'SchoolDistrict' => 'Boston Public Schools',
            'TaxAnnualAmount' => 5000.00,
            'TaxYear' => 2025,
            'AssociationYN' => true,
            'AssociationFee' => 350.00,
            'AssociationFeeFrequency' => 'Monthly',
            'Media' => [
                ['MediaURL' => 'https://photos.example.com/photo1.jpg', 'MediaKey' => 'M1', 'Order' => 0],
                ['MediaURL' => 'https://photos.example.com/photo2.jpg', 'MediaKey' => 'M2', 'Order' => 1],
            ],
        ];
    }
}

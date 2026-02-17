<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Service;

use BMN\Exclusive\Service\ValidationService;
use PHPUnit\Framework\TestCase;

final class ValidationServiceTest extends TestCase
{
    private ValidationService $validator;

    protected function setUp(): void
    {
        $this->validator = new ValidationService();
    }

    // -- validateCreate: valid data --

    public function testValidateCreateWithValidData(): void
    {
        $result = $this->validator->validateCreate([
            'property_type' => 'Residential',
            'list_price' => 500000,
            'street_number' => '123',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    // -- validateCreate: missing required fields --

    public function testValidateCreateMissingAllRequired(): void
    {
        $result = $this->validator->validateCreate([]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('property_type', $result['errors']);
        $this->assertArrayHasKey('list_price', $result['errors']);
        $this->assertArrayHasKey('street_number', $result['errors']);
        $this->assertArrayHasKey('street_name', $result['errors']);
        $this->assertArrayHasKey('city', $result['errors']);
        $this->assertArrayHasKey('state', $result['errors']);
        $this->assertArrayHasKey('postal_code', $result['errors']);
    }

    public function testValidateCreateEmptyStringsAreInvalid(): void
    {
        $result = $this->validator->validateCreate([
            'property_type' => '',
            'list_price' => 500000,
            'street_number' => '',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('property_type', $result['errors']);
        $this->assertArrayHasKey('street_number', $result['errors']);
    }

    // -- validateCreate: property_type validation --

    public function testValidateCreateInvalidPropertyType(): void
    {
        $data = $this->validData();
        $data['property_type'] = 'InvalidType';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('property_type', $result['errors']);
    }

    public function testValidateCreateAllPropertyTypesAccepted(): void
    {
        foreach (ValidationService::PROPERTY_TYPES as $type) {
            $data = $this->validData();
            $data['property_type'] = $type;

            $result = $this->validator->validateCreate($data);

            $this->assertTrue($result['valid'], "Property type '{$type}' should be valid");
        }
    }

    // -- validateCreate: property_sub_type validation --

    public function testValidateCreateValidSubType(): void
    {
        $data = $this->validData();
        $data['property_type'] = 'Residential';
        $data['property_sub_type'] = 'Single Family Residence';

        $result = $this->validator->validateCreate($data);

        $this->assertTrue($result['valid']);
    }

    public function testValidateCreateInvalidSubTypeForType(): void
    {
        $data = $this->validData();
        $data['property_type'] = 'Residential';
        $data['property_sub_type'] = 'Farm';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('property_sub_type', $result['errors']);
    }

    // -- validateCreate: list_price validation --

    public function testValidateCreateZeroPriceInvalid(): void
    {
        $data = $this->validData();
        $data['list_price'] = 0;

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('list_price', $result['errors']);
    }

    public function testValidateCreateNegativePriceInvalid(): void
    {
        $data = $this->validData();
        $data['list_price'] = -100;

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('list_price', $result['errors']);
    }

    // -- validateCreate: state validation --

    public function testValidateCreateInvalidState(): void
    {
        $data = $this->validData();
        $data['state'] = 'Massachusetts';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('state', $result['errors']);
    }

    public function testValidateCreateSingleLetterState(): void
    {
        $data = $this->validData();
        $data['state'] = 'M';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('state', $result['errors']);
    }

    public function testValidateCreateLowercaseStateValid(): void
    {
        // The validator uppercases state before checking, so 'ma' should pass as it becomes 'MA'.
        $data = $this->validData();
        $data['state'] = 'ma';

        $result = $this->validator->validateCreate($data);

        $this->assertTrue($result['valid']);
    }

    // -- validateCreate: postal_code validation --

    public function testValidateCreateValidPostalCode5Digit(): void
    {
        $data = $this->validData();
        $data['postal_code'] = '02101';

        $result = $this->validator->validateCreate($data);

        $this->assertTrue($result['valid']);
    }

    public function testValidateCreateValidPostalCode9Digit(): void
    {
        $data = $this->validData();
        $data['postal_code'] = '02101-1234';

        $result = $this->validator->validateCreate($data);

        $this->assertTrue($result['valid']);
    }

    public function testValidateCreateInvalidPostalCode(): void
    {
        $data = $this->validData();
        $data['postal_code'] = 'ABCDE';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('postal_code', $result['errors']);
    }

    public function testValidateCreateShortPostalCode(): void
    {
        $data = $this->validData();
        $data['postal_code'] = '0210';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('postal_code', $result['errors']);
    }

    // -- validateCreate: status validation --

    public function testValidateCreateInvalidStatus(): void
    {
        $data = $this->validData();
        $data['status'] = 'bogus';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testValidateCreateAllStatusesAccepted(): void
    {
        foreach (ValidationService::STATUSES as $status) {
            $data = $this->validData();
            $data['status'] = $status;

            $result = $this->validator->validateCreate($data);

            $this->assertTrue($result['valid'], "Status '{$status}' should be valid");
        }
    }

    // -- validateCreate: exclusive_tag validation --

    public function testValidateCreateInvalidExclusiveTag(): void
    {
        $data = $this->validData();
        $data['exclusive_tag'] = 'Bogus Tag';

        $result = $this->validator->validateCreate($data);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('exclusive_tag', $result['errors']);
    }

    public function testValidateCreateAllExclusiveTagsAccepted(): void
    {
        foreach (ValidationService::EXCLUSIVE_TAGS as $tag) {
            $data = $this->validData();
            $data['exclusive_tag'] = $tag;

            $result = $this->validator->validateCreate($data);

            $this->assertTrue($result['valid'], "Exclusive tag '{$tag}' should be valid");
        }
    }

    // -- validateUpdate --

    public function testValidateUpdateEmptyDataIsValid(): void
    {
        $result = $this->validator->validateUpdate([]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateUpdatePartialDataValidated(): void
    {
        $result = $this->validator->validateUpdate([
            'list_price' => 600000,
            'city' => 'Cambridge',
        ]);

        $this->assertTrue($result['valid']);
    }

    public function testValidateUpdateInvalidFieldRejected(): void
    {
        $result = $this->validator->validateUpdate([
            'list_price' => -100,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('list_price', $result['errors']);
    }

    // -- validateStatusTransition --

    public function testDraftToActiveAllowed(): void
    {
        $this->assertTrue($this->validator->validateStatusTransition('draft', 'active'));
    }

    public function testDraftToCanceledAllowed(): void
    {
        $this->assertTrue($this->validator->validateStatusTransition('draft', 'canceled'));
    }

    public function testDraftToPendingNotAllowed(): void
    {
        $this->assertFalse($this->validator->validateStatusTransition('draft', 'pending'));
    }

    public function testActiveTransitions(): void
    {
        $this->assertTrue($this->validator->validateStatusTransition('active', 'pending'));
        $this->assertTrue($this->validator->validateStatusTransition('active', 'withdrawn'));
        $this->assertTrue($this->validator->validateStatusTransition('active', 'expired'));
        $this->assertTrue($this->validator->validateStatusTransition('active', 'closed'));
    }

    public function testPendingTransitions(): void
    {
        $this->assertTrue($this->validator->validateStatusTransition('pending', 'active'));
        $this->assertTrue($this->validator->validateStatusTransition('pending', 'closed'));
        $this->assertTrue($this->validator->validateStatusTransition('pending', 'withdrawn'));
    }

    public function testClosedHasNoTransitions(): void
    {
        foreach (ValidationService::STATUSES as $status) {
            $this->assertFalse(
                $this->validator->validateStatusTransition('closed', $status),
                "Closed should not transition to '{$status}'"
            );
        }
    }

    public function testWithdrawnTransitions(): void
    {
        $this->assertTrue($this->validator->validateStatusTransition('withdrawn', 'active'));
        $this->assertTrue($this->validator->validateStatusTransition('withdrawn', 'draft'));
    }

    public function testExpiredTransitions(): void
    {
        $this->assertTrue($this->validator->validateStatusTransition('expired', 'active'));
        $this->assertTrue($this->validator->validateStatusTransition('expired', 'draft'));
    }

    public function testCanceledTransitions(): void
    {
        $this->assertTrue($this->validator->validateStatusTransition('canceled', 'draft'));
        $this->assertFalse($this->validator->validateStatusTransition('canceled', 'active'));
    }

    public function testInvalidFromStatusReturnsFalse(): void
    {
        $this->assertFalse($this->validator->validateStatusTransition('nonexistent', 'active'));
    }

    // -- sanitizeListingData --

    public function testSanitizeTrimsStrings(): void
    {
        $result = $this->validator->sanitizeListingData([
            'city' => '  Boston  ',
            'street_name' => ' Main St ',
        ]);

        $this->assertSame('Boston', $result['city']);
        $this->assertSame('Main St', $result['street_name']);
    }

    public function testSanitizeUppercasesState(): void
    {
        $result = $this->validator->sanitizeListingData(['state' => 'ma']);

        $this->assertSame('MA', $result['state']);
    }

    public function testSanitizeNormalizesPostalCode(): void
    {
        $result = $this->validator->sanitizeListingData(['postal_code' => '021011234']);

        $this->assertSame('02101-1234', $result['postal_code']);
    }

    public function testSanitizeStripsWhitespaceFromPostalCode(): void
    {
        $result = $this->validator->sanitizeListingData(['postal_code' => ' 02101 ']);

        $this->assertSame('02101', $result['postal_code']);
    }

    public function testSanitizeConvertsPriceFieldsToFloat(): void
    {
        $result = $this->validator->sanitizeListingData([
            'list_price' => '500000',
            'original_list_price' => '525000',
            'tax_annual_amount' => '5000',
            'association_fee' => '350',
        ]);

        $this->assertSame(500000.0, $result['list_price']);
        $this->assertSame(525000.0, $result['original_list_price']);
        $this->assertSame(5000.0, $result['tax_annual_amount']);
        $this->assertSame(350.0, $result['association_fee']);
    }

    public function testSanitizeConvertsIntFields(): void
    {
        $result = $this->validator->sanitizeListingData([
            'bedrooms_total' => '3',
            'building_area_total' => '2500',
            'year_built' => '1920',
        ]);

        $this->assertSame(3, $result['bedrooms_total']);
        $this->assertSame(2500, $result['building_area_total']);
        $this->assertSame(1920, $result['year_built']);
    }

    public function testSanitizeConvertsFloatFields(): void
    {
        $result = $this->validator->sanitizeListingData([
            'bathrooms_total' => '2.5',
            'lot_size_acres' => '0.25',
            'latitude' => '42.3601',
            'longitude' => '-71.0589',
        ]);

        $this->assertSame(2.5, $result['bathrooms_total']);
        $this->assertSame(0.25, $result['lot_size_acres']);
        $this->assertEqualsWithDelta(42.3601, $result['latitude'], 0.0001);
        $this->assertEqualsWithDelta(-71.0589, $result['longitude'], 0.0001);
    }

    public function testSanitizeConvertsBooleanFields(): void
    {
        $result = $this->validator->sanitizeListingData([
            'has_pool' => true,
            'has_fireplace' => false,
            'has_basement' => 1,
            'has_hoa' => 0,
            'pet_friendly' => 'yes',
            'waterfront_yn' => '',
        ]);

        $this->assertSame(1, $result['has_pool']);
        $this->assertSame(0, $result['has_fireplace']);
        $this->assertSame(1, $result['has_basement']);
        $this->assertSame(0, $result['has_hoa']);
        $this->assertSame(1, $result['pet_friendly']);
        $this->assertSame(0, $result['waterfront_yn']);
    }

    public function testSanitizeNormalizesBathroomsTotalToFullAndHalf(): void
    {
        $result = $this->validator->sanitizeListingData([
            'bathrooms_total' => 2.5,
        ]);

        $this->assertSame(2.5, $result['bathrooms_total']);
        $this->assertSame(2, $result['bathrooms_full']);
        $this->assertSame(1, $result['bathrooms_half']);
    }

    public function testSanitizeNormalizesBathroomsFullAndHalfToTotal(): void
    {
        $result = $this->validator->sanitizeListingData([
            'bathrooms_full' => 3,
            'bathrooms_half' => 1,
        ]);

        $this->assertSame(3.5, $result['bathrooms_total']);
    }

    public function testSanitizeDoesNotOverwriteExistingBathrooms(): void
    {
        // When all three are provided, no normalization happens.
        $result = $this->validator->sanitizeListingData([
            'bathrooms_total' => 2.5,
            'bathrooms_full' => 2,
            'bathrooms_half' => 1,
        ]);

        $this->assertSame(2.5, $result['bathrooms_total']);
        $this->assertSame(2, $result['bathrooms_full']);
        $this->assertSame(1, $result['bathrooms_half']);
    }

    // -- getOptions --

    public function testGetOptionsReturnsAllArrays(): void
    {
        $options = $this->validator->getOptions();

        $this->assertArrayHasKey('property_types', $options);
        $this->assertArrayHasKey('property_sub_types', $options);
        $this->assertArrayHasKey('statuses', $options);
        $this->assertArrayHasKey('exclusive_tags', $options);
        $this->assertArrayHasKey('status_transitions', $options);
    }

    public function testGetOptionsPropertyTypesMatch(): void
    {
        $options = $this->validator->getOptions();

        $this->assertSame(ValidationService::PROPERTY_TYPES, $options['property_types']);
    }

    public function testGetOptionsStatusesMatch(): void
    {
        $options = $this->validator->getOptions();

        $this->assertSame(ValidationService::STATUSES, $options['statuses']);
    }

    // -- helper --

    private function validData(): array
    {
        return [
            'property_type' => 'Residential',
            'list_price' => 500000,
            'street_number' => '123',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ];
    }
}

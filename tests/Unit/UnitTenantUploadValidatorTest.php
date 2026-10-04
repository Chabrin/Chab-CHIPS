<?php

namespace Tests\Unit;

use App\Services\UnitTenantUpload\UnitTenantUploadLookups;
use App\Services\UnitTenantUpload\UnitTenantUploadValidator;
use PHPUnit\Framework\TestCase;

class UnitTenantUploadValidatorTest extends TestCase
{
    private function validator(): UnitTenantUploadValidator
    {
        return new UnitTenantUploadValidator(UnitTenantUploadLookups::fromArray([
            'unit_types' => ['Normal' => ['id' => 1, 'name' => 'Normal'], 'Owner Occupied' => ['id' => 2, 'name' => 'Owner Occupied']],
            'usage_types' => ['Residential' => ['id' => 2, 'name' => 'Residential']],
            'topologies' => ['1 Bedroom' => ['id' => 3, 'name' => '1 Bedroom'], 'SR' => ['id' => 8, 'name' => 'Single Room']],
            'tenant_types' => ['INDIVIDUAL' => ['id' => 1, 'name' => 'INDIVIDUAL'], 'COMPANY' => ['id' => 2, 'name' => 'COMPANY'], 'OWNERS' => ['id' => 4, 'name' => 'OWNERS']],
            'categories' => [],
            'countries' => ['Kenya' => ['id' => 1, 'name' => 'Kenya']],
            'genders' => ['MALE' => ['id' => 1, 'name' => 'MALE'], 'FEMALE' => ['id' => 2, 'name' => 'FEMALE']],
        ]));
    }

    private function unit(array $over = []): array
    {
        return $over + ['row_no' => 2, 'unit_number' => 'A1', 'unit_name' => 'Unit A1', 'unit_type' => null, 'usage_type' => null, 'topology' => '1 Bedroom', 'rent_amount' => '1000', 'vat_able' => null, 'initial_water_reading' => '0', 'unit_category' => null];
    }

    private function individual(array $over = []): array
    {
        return $over + ['row_no' => 2, 'sheet' => 'individual', 'unit_number' => 'A1', 'tenant_type' => null, 'lease_start_date' => '2026-11-01', 'mobile_no' => '0712345678', 'names' => 'Jane', 'national_id' => '123', 'passport_no' => null, 'other_contact_phone' => '1', 'other_contact_name' => 'Bob'];
    }

    private function corporate(array $over = []): array
    {
        return $over + ['row_no' => 2, 'sheet' => 'corporate', 'unit_number' => 'A1', 'tenant_type' => null, 'lease_start_date' => '2026-11-01', 'mobile_no' => '0712345678', 'company_name' => 'Acme', 'registration_number' => 'R1', 'contact_phone' => '1', 'contact_name' => 'Cat'];
    }

    private function check(array $units, array $tenants, array $existing = []): array
    {
        return $this->validator()->validate($units, $tenants, $existing + ['unit_numbers' => [], 'national_ids' => [], 'passports' => [], 'registrations' => []]);
    }

    public function test_a_complete_unit_and_individual_tenant_pass(): void
    {
        $result = $this->check([1 => $this->unit()], [10 => $this->individual()]);

        $this->assertNull($result['units'][1]);
        $this->assertNull($result['tenants'][10]);
    }

    public function test_unit_required_fields_and_unknown_lookups_are_reported(): void
    {
        $result = $this->check([1 => $this->unit(['unit_name' => null, 'topology' => 'Nonsense', 'rent_amount' => 'abc', 'vat_able' => '2'])], []);

        $this->assertStringContainsString('unit_name required', $result['units'][1]);
        $this->assertStringContainsString("Topology 'Nonsense' not recognised", $result['units'][1]);
        $this->assertStringContainsString('Rent Amount', $result['units'][1]);
        $this->assertStringContainsString('vat_able must be 0 or 1', $result['units'][1]);
    }

    public function test_topology_matches_by_name_or_slug_ignoring_case_and_spaces(): void
    {
        $result = $this->check([1 => $this->unit(['topology' => '1bedroom']), 2 => $this->unit(['unit_number' => 'A2', 'row_no' => 3, 'topology' => 'sr'])], []);

        $this->assertNull($result['units'][1]);
        $this->assertNull($result['units'][2]);
    }

    public function test_duplicate_and_existing_unit_numbers_are_reported(): void
    {
        $result = $this->check([1 => $this->unit(), 2 => $this->unit(['row_no' => 3, 'unit_number' => 'a1'])], [], ['unit_numbers' => ['a1' => true]]);

        $this->assertStringContainsString('more than one row', $result['units'][1]);
        $this->assertStringContainsString('already exists under this property', $result['units'][1]);
    }

    public function test_individual_needs_national_id_or_passport(): void
    {
        $neither = $this->check([1 => $this->unit()], [10 => $this->individual(['national_id' => null])]);
        $passportOnly = $this->check([1 => $this->unit()], [10 => $this->individual(['national_id' => null, 'passport_no' => 'P1'])]);

        $this->assertStringContainsString('National ID or Passport No required', $neither['tenants'][10]);
        $this->assertNull($passportOnly['tenants'][10]);
    }

    public function test_tenant_type_must_fit_its_sheet(): void
    {
        $wrongIndividual = $this->check([1 => $this->unit()], [10 => $this->individual(['tenant_type' => 'COMPANY'])]);
        $wrongCorporate = $this->check([1 => $this->unit()], [10 => $this->corporate(['tenant_type' => 'INDIVIDUAL'])]);
        $owner = $this->check([1 => $this->unit()], [10 => $this->corporate(['tenant_type' => 'OWNERS'])]);

        $this->assertStringContainsString('COMPANY belongs on the corporate_tenants sheet', $wrongIndividual['tenants'][10]);
        $this->assertStringContainsString('INDIVIDUAL belongs on the individual_tenants sheet', $wrongCorporate['tenants'][10]);
        $this->assertNull($owner['tenants'][10]);
    }

    public function test_tenant_must_match_its_unit_row_and_a_unit_has_one_tenant(): void
    {
        $orphan = $this->check([1 => $this->unit()], [10 => $this->individual(['unit_number' => 'ZZ'])]);
        $moved = $this->check([1 => $this->unit()], [10 => $this->individual(['row_no' => 5])]);
        $two = $this->check([1 => $this->unit()], [10 => $this->individual(), 11 => $this->corporate()]);

        $this->assertStringContainsString('is not on the units sheet', $orphan['tenants'][10]);
        $this->assertStringContainsString('does not match its row', $moved['tenants'][10]);
        $this->assertStringContainsString('more than one tenant', $two['tenants'][10]);
    }

    public function test_dates_emails_nationality_and_gender_are_checked(): void
    {
        $result = $this->check([1 => $this->unit()], [10 => $this->individual(['lease_start_date' => 'not a date', 'email' => 'bad', 'nationality' => 'Atlantis', 'gender' => 'Robot'])]);

        $this->assertStringContainsString("Lease Start Date 'not a date' must be a real date written YYYY-MM-DD", $result['tenants'][10]);
        $this->assertStringContainsString('not a valid email', $result['tenants'][10]);
        $this->assertStringContainsString("Nationality 'Atlantis' not recognised", $result['tenants'][10]);
        $this->assertStringContainsString("Gender 'Robot'", $result['tenants'][10]);
    }

    public function test_existing_identifiers_are_reported(): void
    {
        $result = $this->check([1 => $this->unit()], [10 => $this->individual()], ['national_ids' => ['123' => true]]);

        $this->assertStringContainsString('already belongs to an existing tenant', $result['tenants'][10]);
    }

    public function test_corporate_required_fields_and_duplicate_registration(): void
    {
        $missing = $this->check([1 => $this->unit()], [10 => $this->corporate(['company_name' => null, 'contact_name' => null])]);
        $existing = $this->check([1 => $this->unit()], [10 => $this->corporate()], ['registrations' => ['r1' => true]]);

        $this->assertStringContainsString('company_name, contact_name required', $missing['tenants'][10]);
        $this->assertStringContainsString('Registration Number', $existing['tenants'][10]);
    }

    public function test_lease_dates_must_be_strict_yyyy_mm_dd(): void
    {
        foreach (['05/03/2027', 'tomorrow', '2027-02-30', '9999-12-31', '2027', '20270305'] as $bad) {
            $result = $this->check([1 => $this->unit()], [10 => $this->individual(['lease_start_date' => $bad])]);
            $this->assertStringContainsString('YYYY-MM-DD', $result['tenants'][10], $bad);
        }

        $ok = $this->check([1 => $this->unit()], [10 => $this->individual(['lease_start_date' => '2027-03-05'])]);
        $this->assertNull($ok['tenants'][10]);
    }

    public function test_long_values_and_out_of_range_numbers_are_reported(): void
    {
        $result = $this->check([1 => $this->unit(['unit_name' => str_repeat('x', 256), 'rent_amount' => '100000000000', 'initial_water_reading' => '10.5'])], []);

        $this->assertStringContainsString('unit_name is longer than 255 characters', $result['units'][1]);
        $this->assertStringContainsString('Rent Amount is too large', $result['units'][1]);
        $this->assertStringContainsString('must be a whole number', $result['units'][1]);
    }

    public function test_next_of_kin_details_need_a_name(): void
    {
        $result = $this->check([1 => $this->unit()], [10 => $this->individual(['nok_phone' => '0700'])]);

        $this->assertStringContainsString('nok_name required', $result['tenants'][10]);
    }

    public function test_unit_numbers_and_identifiers_only_allow_plain_characters(): void
    {
        foreach (['ÉTAGE-1', 'A#1', 'A*2', "A'1"] as $bad) {
            $result = $this->check([1 => $this->unit(['unit_number' => $bad])], []);
            $this->assertStringContainsString('may only contain letters A-Z', $result['units'][1], $bad);
        }

        foreach (['1J-12', 'A/3', 'Shop 4', '12.5', 'a1'] as $good) {
            $this->assertNull($this->check([1 => $this->unit(['unit_number' => $good])], [])['units'][1], $good);
        }

        $id = $this->check([1 => $this->unit()], [10 => $this->individual(['national_id' => 'ÉÉ123'])]);
        $this->assertStringContainsString('National ID', $id['tenants'][10]);
        $this->assertStringContainsString('may only contain', $id['tenants'][10]);

        $company = $this->check([1 => $this->unit()], [10 => $this->corporate(['registration_number' => 'R€1'])]);
        $this->assertStringContainsString('Registration Number', $company['tenants'][10]);

        foreach (['P-6622132', '823-11166', '10000+254', 'PVT VQ10'] as $realId) {
            $this->assertNull($this->check([1 => $this->unit()], [10 => $this->individual(['national_id' => $realId])])['tenants'][10], $realId);
        }
    }

    public function test_rent_must_be_a_clean_amount(): void
    {
        foreach (['1000', '1,000', '25,000.50', '0', '12.5'] as $ok) {
            $this->assertNull($this->check([1 => $this->unit(['rent_amount' => $ok])], [])['units'][1], $ok);
        }

        foreach (['1,2,3', '100.555', '-5', 'abc', '1e5', '1,00'] as $bad) {
            $this->assertStringContainsString('not a valid amount', $this->check([1 => $this->unit(['rent_amount' => $bad])], [])['units'][1], $bad);
        }
    }
}

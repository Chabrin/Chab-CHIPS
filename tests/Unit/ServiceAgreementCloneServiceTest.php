<?php

namespace Tests\Unit;

use App\Models\InvoiceableItem;
use App\Models\InvoiceableItemCycle;
use App\Models\ServiceAgreement;
use App\Models\SlaInvoiceableItem;
use App\Services\ServiceAgreementCloneService;
use Tests\TestCase;

class ServiceAgreementCloneServiceTest extends TestCase
{
    private function sla(array $overrides = []): ServiceAgreement
    {
        return new ServiceAgreement(array_merge([
            'commission_percentage' => 10,
            'vat' => 'Yes',
            'vat_per' => 16,
            'flat_rate_vat' => 0,
            'auto_renewal' => '1',
            'move_in_inspection_required' => true,
            'landlord_payment_date' => 6,
            'years' => 2,
            'returns_combination' => 'all_combined',
        ], $overrides));
    }

    public function test_the_commercial_terms_are_copied_onto_the_form_fields(): void
    {
        $terms = (new ServiceAgreementCloneService())->mapTerms($this->sla(), []);

        $this->assertEquals(10, $terms['commission_percentage']);
        $this->assertSame('Yes', $terms['vat_able']);
        $this->assertEquals(16, $terms['vat_percentage']);
        $this->assertSame('1', $terms['auto_renewal']);
        $this->assertSame('1', $terms['move_in_inspection_required']);
        $this->assertEquals(6, $terms['payment_date']);
        $this->assertEquals(2, $terms['duration_years']);
        $this->assertSame('all_combined', $terms['returns_combination']);
    }

    public function test_nothing_that_belongs_to_the_original_agreement_is_copied(): void
    {
        $terms = (new ServiceAgreementCloneService())->mapTerms($this->sla([
            'property_id' => 99,
            'reference_number' => 'Villa-00001',
            'current_status_id' => 5,
            'date_start' => '2026-01-01',
            'date_end' => '2028-01-01',
            'charge_registration_fee' => true,
        ]), []);

        foreach (['property_id', 'client_id', 'reference_number', 'current_status_id', 'date_start', 'date_end', 'charge_registration_fee', 'sla_documents'] as $key) {
            $this->assertArrayNotHasKey($key, $terms);
        }
    }

    public function test_auto_renewal_is_translated_from_both_stored_styles_to_the_create_form_values(): void
    {
        $service = new ServiceAgreementCloneService();

        $this->assertSame('1', $service->formAutoRenewal('Yes'));
        $this->assertSame('1', $service->formAutoRenewal('1'));
        $this->assertSame('1', $service->formAutoRenewal(1));
        $this->assertSame('2', $service->formAutoRenewal('No'));
        $this->assertSame('2', $service->formAutoRenewal('2'));
        $this->assertSame('2', $service->formAutoRenewal(null));
    }

    public function test_an_unrecognised_vat_mode_falls_back_to_no_and_flat_rate_is_kept(): void
    {
        $service = new ServiceAgreementCloneService();

        $this->assertSame('No', $service->mapTerms($this->sla(['vat' => null]), [])['vat_able']);
        $this->assertSame('No', $service->mapTerms($this->sla(['vat' => 'whatever']), [])['vat_able']);
        $this->assertSame('Flat Rate', $service->mapTerms($this->sla(['vat' => 'Flat Rate', 'flat_rate_vat' => 2500]), [])['vat_able']);
    }

    public function test_migrated_sla_with_no_duration_or_combination_gets_safe_defaults(): void
    {
        $terms = (new ServiceAgreementCloneService())->mapTerms($this->sla([
            'years' => null,
            'returns_combination' => null,
            'move_in_inspection_required' => false,
        ]), []);

        $this->assertNull($terms['duration_years']);
        $this->assertSame('each_separate', $terms['returns_combination']);
        $this->assertSame('0', $terms['move_in_inspection_required']);
    }

    public function test_an_item_with_an_amount_is_copied_as_an_amount(): void
    {
        $row = $this->itemRow(['item_id' => 7, 'amount' => 1500, 'percentage_of_rent' => null]);

        $item = (new ServiceAgreementCloneService())->itemRow($row);

        $this->assertSame(7, $item['item_id']);
        $this->assertSame('Water Deposit', $item['item_name']);
        $this->assertSame('WTR', $item['billing_code']);
        $this->assertSame('One-Time', $item['default_cycle_name']);
        $this->assertSame('amount', $item['choice']);
    }

    public function test_an_item_with_a_percentage_of_rent_is_copied_as_a_percentage(): void
    {
        $row = $this->itemRow(['item_id' => 8, 'amount' => null, 'percentage_of_rent' => 100]);

        $item = (new ServiceAgreementCloneService())->itemRow($row);

        $this->assertSame('percentage', $item['choice']);
    }

    public function test_an_item_with_no_value_is_copied_with_no_choice(): void
    {
        $row = $this->itemRow(['item_id' => 9, 'amount' => null, 'percentage_of_rent' => null]);

        $this->assertNull((new ServiceAgreementCloneService())->itemRow($row)['choice']);
    }

    public function test_an_item_whose_master_record_no_longer_exists_is_not_copied(): void
    {
        $row = new SlaInvoiceableItem(['item_id' => 10, 'amount' => 500]);
        $row->setRelation('item', null);

        $this->assertNull((new ServiceAgreementCloneService())->itemRow($row));
    }

    private function itemRow(array $attributes): SlaInvoiceableItem
    {
        $item = new InvoiceableItem(['item_name' => 'Water Deposit', 'billing_code' => 'WTR']);
        $item->setRelation('cycle', new InvoiceableItemCycle(['cycle_name' => 'One-Time']));

        $row = new SlaInvoiceableItem($attributes);
        $row->setRelation('item', $item);

        return $row;
    }
}

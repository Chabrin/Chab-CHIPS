<?php

namespace Tests\Unit;

use App\Exceptions\ClientAccountException;
use App\Services\GlJournalPoster;
use App\Services\LandlordPaidOwnerService;
use App\Services\RecoveryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LandlordPaidOwnerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_pm_debtors_invoices', function ($table) {
            $table->increments('id');
            $table->integer('property_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('tenant_id')->nullable();
        });
        Schema::create('sys_pm_properties', function ($table) {
            $table->increments('id');
            $table->integer('client_id')->nullable();
            $table->string('property_name')->nullable();
        });
        Schema::create('sys_pm_service_agreements', function ($table) {
            $table->increments('id');
            $table->integer('property_id')->nullable();
            $table->integer('client_d')->nullable();
        });

        // landlord 7 owns property 1 (named on the property); landlord 9 owns property 2 (named on its agreement)
        DB::table('sys_pm_properties')->insert([['id' => 1, 'client_id' => 7], ['id' => 2, 'client_id' => null], ['id' => 3, 'client_id' => 8]]);
        DB::table('sys_pm_service_agreements')->insert(['property_id' => 2, 'client_d' => 9]);
        DB::table('sys_pm_debtors_invoices')->insert([['id' => 10, 'property_id' => 1, 'unit_id' => 1, 'tenant_id' => 5], ['id' => 20, 'property_id' => 3, 'unit_id' => 2, 'tenant_id' => 6]]);
    }

    private function service(): LandlordPaidOwnerService
    {
        return new LandlordPaidOwnerService(new RecoveryService(new GlJournalPoster()));
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');
    }

    public function test_a_landlords_properties_are_the_ones_naming_them_on_the_property_or_on_an_agreement(): void
    {
        $this->assertSame([1], $this->service()->propertyIds(7));
        $this->assertSame([2], $this->service()->propertyIds(9));
        $this->assertSame([], $this->service()->propertyIds(99));
    }

    public function test_a_landlord_cannot_report_on_someone_elses_invoice(): void
    {
        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('not for one of your properties');

        $this->service()->report(7, ['invoice_id' => 20, 'amount' => 1000, 'paid_on' => date('Y-m-d')], $this->pdf());
    }

    public function test_the_payment_date_cannot_be_in_the_future_or_missing(): void
    {
        foreach ([date('Y-m-d', strtotime('+2 days')), '', 'not a date'] as $date) {
            try {
                $this->service()->report(7, ['invoice_id' => 10, 'amount' => 1000, 'paid_on' => $date], $this->pdf());
                $this->fail('A report dated "' . $date . '" must be refused');
            } catch (ClientAccountException $e) {
                $this->assertStringContainsString('date the tenant paid you', $e->getMessage());
            }
        }
    }

    public function test_a_report_without_a_pdf_proof_is_refused(): void
    {
        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('Attach a PDF');

        $this->service()->report(7, ['invoice_id' => 10, 'amount' => 1000, 'paid_on' => date('Y-m-d'), 'reference' => 'ABC123'], null);
    }

    public function test_an_unknown_invoice_is_refused(): void
    {
        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('Choose the invoice');

        $this->service()->report(7, ['invoice_id' => 999, 'amount' => 1000, 'paid_on' => date('Y-m-d')], $this->pdf());
    }

    public function test_the_link_is_signed_expires_and_carries_only_the_landlord(): void
    {
        $url = $this->service()->link(7);

        $this->assertStringContainsString('/paid-owner/report/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringNotContainsString('/7', parse_url($url, PHP_URL_PATH));
    }
}

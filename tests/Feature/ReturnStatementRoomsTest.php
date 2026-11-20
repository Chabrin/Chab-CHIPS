<?php

namespace Tests\Feature;

use App\Exports\ReturnStatementExport;
use App\Models\ReturnClient;
use App\Models\ReturnSummary;
use App\Services\ReturnStatementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReturnStatementRoomsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'return_rooms_test', 'database.connections.return_rooms_test' =>
            ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('return_rooms_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('sys_pm_properties', function (Blueprint $t) { $t->id(); $t->string('reference_number'); });
        Schema::create('sys_pm_units', function (Blueprint $t) {
            $t->id(); $t->integer('property_id'); $t->string('unit_number')->nullable(); $t->string('unit_code'); $t->timestamp('date_time')->nullable();
        });
        Schema::create('sys_pm_clients', function (Blueprint $t) { $t->id(); $t->string('names'); });
        Schema::create('sys_pm_lease_agreements', function (Blueprint $t) {
            $t->id(); $t->integer('unit_id'); $t->integer('tenant_id'); $t->integer('current_status_id');
            $t->date('date_start')->nullable(); $t->date('date_end')->nullable();
        });
        DB::table('sys_pm_properties')->insert([['id' => 1, 'reference_number' => '442F'], ['id' => 2, 'reference_number' => 'OTHER']]);
        foreach ([4, 5, 6, 10] as $number) {
            DB::table('sys_pm_units')->insert(['id' => $number, 'property_id' => 1, 'unit_number' => (string) $number,
                'unit_code' => '442F-'.$number, 'date_time' => '2026-11-01']);
        }
        DB::table('sys_pm_units')->insert([
            ['id' => 7, 'property_id' => 1, 'unit_number' => '7', 'unit_code' => '442F-7', 'date_time' => '2026-12-01'],
            ['id' => 8, 'property_id' => 2, 'unit_number' => '8', 'unit_code' => 'OTHER-8', 'date_time' => null],
        ]);
        DB::table('sys_pm_clients')->insert(['id' => 1, 'names' => 'Recorded tenant']);
        DB::table('sys_pm_lease_agreements')->insert(['unit_id' => 10, 'tenant_id' => 1, 'current_status_id' => 5,
            'date_start' => '2026-11-01', 'date_end' => null]);
    }

    private function statement(array $overrides = []): array
    {
        $return = new ReturnSummary(array_merge(['id' => 1, 'return_code' => 'R1TEST', 'property_code' => '442F',
            'return_type' => 'MAINRETURN', 'year' => 2026, 'month' => 11, 'approved_status' => 0, 'paid_status' => 0], $overrides));
        $lines = collect([
            (new ReturnClient)->forceFill(['id' => 1, 'property_code' => '442F', 'unit_code' => '442F-4', 'unit_id' => 4,
                'entry_type' => 'RENT CURRENT', 'tenant_name' => 'Tenant 4', 'net_amount' => 17000, 'amount_payable_landlord' => 17000, 'is_vatable' => 'No']),
            (new ReturnClient)->forceFill(['id' => 2, 'property_code' => '442F', 'unit_code' => '6', 'unit_id' => 6,
                'entry_type' => 'RENT CURRENT', 'tenant_name' => 'Tenant 6', 'net_amount' => 17000, 'amount_payable_landlord' => 17000, 'is_vatable' => 'No']),
        ]);
        return [$return, app(ReturnStatementService::class)->statement($return, $lines, collect()), $lines];
    }

    public function test_draft_main_return_includes_vacant_and_unbilled_rooms_without_changing_financials(): void
    {
        [$return, $data, $lines] = $this->statement();
        $this->assertSame(['4', '5', '6', '10'], array_column($data['tenantRows'], 'unit_code'));
        $vacant = collect($data['tenantRows'])->firstWhere('unit_code', '5');
        $this->assertSame('[VACANT]', $vacant['tenant_name']);
        $this->assertSame(0.0, $vacant['monthly_rent']);
        $this->assertSame(0.0, $vacant['payable']);
        $this->assertNull($vacant['remarks_line_id']);
        $this->assertSame('Recorded tenant', collect($data['tenantRows'])->firstWhere('unit_code', '10')['tenant_name']);
        [, $historical] = $this->statement(['approved_status' => 1]);
        foreach (['totals', 'summary', 'settlementRows', 'arrears'] as $key) $this->assertSame($historical[$key], $data[$key]);
        $this->assertSame(2, $lines->count());
    }

    public function test_approved_paid_mini_and_master_returns_keep_only_recorded_rooms(): void
    {
        foreach ([['approved_status' => 1], ['paid_status' => 1], ['return_type' => 'MINIRETURN'], ['return_type' => 'MASTERRETURN']] as $state) {
            [, $data] = $this->statement($state);
            $this->assertSame(['4', '6'], array_column($data['tenantRows'], 'unit_code'));
        }
    }

    public function test_ended_and_future_leases_do_not_mislabel_a_vacant_room(): void
    {
        foreach ([['date_start' => '2026-10-01', 'date_end' => '2026-10-31'], ['date_start' => '2026-12-01', 'date_end' => null]] as $dates) {
            DB::table('sys_pm_lease_agreements')->update($dates);
            [, $data] = $this->statement();
            $this->assertSame('[VACANT]', collect($data['tenantRows'])->firstWhere('unit_code', '10')['tenant_name']);
        }
    }

    public function test_screen_pdf_and_excel_include_the_same_missing_room(): void
    {
        [$return, $data] = $this->statement();
        $html = view('property.processed-returns.partials.statement', $data + ['return' => $return])->render();
        $this->assertStringContainsString('[VACANT]', $html);
        $this->assertSame(2, substr_count($html, 'class="statement-remarks-form"'));
        $pdfHtml = view('property.processed-returns.pdf', $data + ['return' => $return,
            'exportInfo' => ['title' => 'November 2026', 'type' => 'MAINRETURN'], 'signoff' => []])->render();
        $this->assertStringContainsString('[VACANT]', $pdfHtml);
        $header = ['title' => 'Return', 'property_label' => '442F', 'landlord_label' => 'Test landlord', 'period' => '11/2026'];
        $book = app(ReturnStatementExport::class)->build($return, $data, $header, []);
        $this->assertSame('5', $book->getActiveSheet()->getCell('A10')->getValue());
        $this->assertSame('[VACANT]', $book->getActiveSheet()->getCell('B10')->getValue());
    }
}

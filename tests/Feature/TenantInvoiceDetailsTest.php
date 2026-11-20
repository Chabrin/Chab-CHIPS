<?php

namespace Tests\Feature;

use App\Http\Controllers\InvoicingController;
use App\Models\{Client, DebtorInvoice, Deposit, InvoiceableItem, LeaseAgreement, Property, TenantAccountTransaction, TenantPayment, TenantPaymentAllocation, Unit, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, Crypt, DB, Event, Schema};
use Tests\TestCase;

class TenantInvoiceDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'invoice_details_test', 'database.connections.invoice_details_test' =>
            ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('invoice_details_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Event::fake();
        foreach ([new Client, new DebtorInvoice, new Deposit, new InvoiceableItem, new LeaseAgreement,
            new Property, new TenantAccountTransaction, new TenantPayment, new TenantPaymentAllocation, new Unit] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                if ($model instanceof Property) $table->boolean('active')->default(true);
                foreach (array_diff($model->getFillable(), ['id']) as $field) $table->text($field)->nullable();
            });
        }
        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->boolean('is_global'); });
        Schema::create('cb_role_user', function (Blueprint $t) { $t->integer('role_id'); $t->integer('user_id'); });
        Schema::create('cb_sub_modules', function (Blueprint $t) { $t->id(); $t->string('sub_module_url'); });
        Schema::create('cb_sub_module_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('sub_module_id'); });
        Schema::create('sys_user_staff_links', function (Blueprint $t) { $t->integer('user_id'); $t->integer('staff_id'); });
        Schema::create('sys_set_zones', function (Blueprint $t) { $t->id(); $t->integer('zone_manager_id')->nullable(); $t->integer('zone_supervisor_id')->nullable(); });
        DB::table('josya_users')->insert(['id' => 1, 'name' => 'Billing officer']);
        DB::table('cb_roles')->insert(['id' => 1, 'is_global' => true]);
        DB::table('cb_role_user')->insert(['role_id' => 1, 'user_id' => 1]);
        DB::table('cb_sub_modules')->insert(['id' => 1, 'sub_module_url' => '/tenants/invoiceitems']);
        DB::table('cb_sub_module_permissions')->insert(['role_id' => 1, 'sub_module_id' => 1]);
        DB::table('sys_pm_properties')->insert(['id' => 1, 'active' => true, 'property_name' => 'Test property']);
        DB::table('sys_set_invoiceable_items')->insert(['id' => 1, 'item_name' => 'water Depost', 'billing_code' => '2002']);
        DB::table('sys_pm_debtors_invoices')->insert([
            'id' => 1, 'debtor_type_id' => 2, 'property_id' => 1, 'item_type_id' => 1,
            'reference_number' => 'Invoice <test>', 'return_type_id' => 'Original label',
            'total_amount' => 17000, 'current_balance' => 16000, 'current_status_id' => 8,
            'created_by' => 1, 'batch_number' => 'S123', 'date_time' => '2026-11-19 12:00:00',
        ]);
        $this->actingAs(User::findOrFail(1));
    }

    private function details()
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => auth()->user());
        return app(InvoicingController::class)->tenantInvoiceDetails($request, Crypt::encryptString('1'));
    }

    public function test_details_render_saved_and_current_item_values_and_linked_records_without_writes(): void
    {
        DB::table('sys_pm_deposits')->insert(['invoice_id' => 1, 'amount' => 17000, 'months' => 1, 'held_by' => 'Company']);
        DB::table('sys_pm_tenants_payments_invoices')->insert(['invoice_id' => 1, 'payment_id' => 99, 'amount_allocated' => 1000]);
        DB::table('sys_pm_tenants_accounts_trans')->insert(['invoice_id' => 1, 'debit_amount' => 17000, 'credit_amount' => 0, 'narration' => 'Saved ledger charge']);
        DB::enableQueryLog();
        $view = $this->details();
        $source = str_replace("@extends('layouts.main')", '', file_get_contents(resource_path('views/property/invoices/tenant-invoice-details.blade.php')));
        $html = Blade::render($source."\n@yield('content')", $view->getData(), true);
        foreach (['Invoice &lt;test&gt;', 'water Depost', '2002', 'Original label', 'Billing officer', '17,000.00', '16,000.00', '1,000.00', 'Company', 'Saved ledger charge', 'S123'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (DB::getQueryLog() as $query) $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b/i', $query['query']);
    }

    public function test_missing_linked_records_render_clear_empty_states(): void
    {
        $view = $this->details();
        $this->assertCount(0, $view->getData()['deposits']);
        $source = str_replace("@extends('layouts.main')", '', file_get_contents(resource_path('views/property/invoices/tenant-invoice-details.blade.php')));
        $html = Blade::render($source."\n@yield('content')", $view->getData(), true);
        $this->assertStringContainsString('No deposit record is linked', $html);
        $this->assertStringContainsString('No payments allocated', $html);
    }

    public function test_invoice_search_provides_a_working_encrypted_details_link(): void
    {
        $response = app(InvoicingController::class)->searchInvoiceItems(Request::create('/', 'POST'));
        $this->assertSame(200, $response->getStatusCode());
        $row = $response->getData(true)['data'][0];
        $this->assertSame('Invoice <test>', $row['reference_number']);
        $route = app('router')->getRoutes()->match(Request::create($row['details_url']));
        $this->assertSame('invoices.tenant.items.show', $route->getName());
        $this->assertSame('1', Crypt::decryptString($route->parameter('encryptedId')));
    }

    public function test_menu_access_is_required_for_direct_links(): void
    {
        DB::table('cb_sub_module_permissions')->delete();
        $this->assertDenied(Crypt::encryptString('1'), 403);
    }

    public function test_client_invoices_invalid_tokens_and_other_staff_properties_are_not_exposed(): void
    {
        $this->assertDenied('invalid-token', 404);
        DB::table('sys_pm_debtors_invoices')->where('id', 1)->update(['debtor_type_id' => 1]);
        $this->assertDenied(Crypt::encryptString('1'), 404);
        DB::table('sys_pm_debtors_invoices')->where('id', 1)->update(['debtor_type_id' => 2]);
        DB::table('cb_roles')->update(['is_global' => false]);
        DB::table('sys_user_staff_links')->insert(['user_id' => 1, 'staff_id' => 50]);
        $this->assertDenied(Crypt::encryptString('1'), 404);
    }

    private function assertDenied(string $token, int $status): void
    {
        // Avoid rendering the application's error layout, whose menu composers need the full database.
        $this->withoutMiddleware()->withoutExceptionHandling();
        try {
            $this->get(route('invoices.tenant.items.show', $token));
            $this->fail('Invoice details should not have been accessible.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertSame(404, $status);
        }
    }
}

<?php

namespace Tests\Support;

use App\Models\Client;
use App\Models\ClientAdvance;
use App\Models\ClientBankAccount;
use App\Models\ClientBankDetail;
use App\Models\DebtorInvoice;
use App\Models\Deposit;
use App\Models\DepositAllocation;
use App\Models\InvoiceableItem;
use App\Models\InvoiceableItemCycle;
use App\Models\LandlordAccount;
use App\Models\LeaseAgreement;
use App\Models\PaymentVoucherHeader;
use App\Models\PaymentVoucherLine;
use App\Models\Property;
use App\Models\PropertyBankDetail;
use App\Models\PropertyOwnershipTransfer;
use App\Models\RecurrentClientRemittance;
use App\Models\ReturnsBatchConfigPropertyMapping;
use App\Models\ReturnsBatchConfiguration;
use App\Models\ReturnSummary;
use App\Models\ServiceAgreement;
use App\Models\ServiceAgreementBillCharge;
use App\Models\ServiceAgreementDocument;
use App\Models\ServiceAgreementInvoiceClient;
use App\Models\ServiceAgreementReturnItem;
use App\Models\ServiceAgreementReturnType;
use App\Models\SlaInvoiceableItem;
use App\Models\TenantAccountTransaction;
use App\Models\TenantPayment;
use App\Models\TenantPaymentAllocation;
use App\Models\TenantRecord;
use App\Models\Unit;
use App\Models\User;
use App\Services\PropertyOwnershipTransferService;
use App\Services\ServiceAgreementApprovalService;
use Carbon\Carbon;
use Database\Seeders\PropertyOwnershipTransferMenuSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait PropertyOwnershipTransferFixtures
{
    private PropertyOwnershipTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'ownership_test', 'database.connections.ownership_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('ownership_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2026_01_23_115917_create_notifications_table.php'))->up();
        $this->travelTo(Carbon::parse('2026-12-01 09:00:00'));
        Event::fake();
        foreach ([new Client, new TenantRecord, new ClientBankDetail, new ClientBankAccount, new Property, new Unit, new LeaseAgreement,
            new ServiceAgreement, new LandlordAccount, new DebtorInvoice, new Deposit, new ClientAdvance,
            new RecurrentClientRemittance, new PropertyBankDetail, new ReturnSummary, new TenantPayment,
            new TenantAccountTransaction, new TenantPaymentAllocation, new DepositAllocation,
            new SlaInvoiceableItem, new InvoiceableItem, new InvoiceableItemCycle, new ServiceAgreementDocument, new ServiceAgreementBillCharge, new ServiceAgreementInvoiceClient,
            new ServiceAgreementReturnType, new ServiceAgreementReturnItem, new ReturnsBatchConfiguration,
            new ReturnsBatchConfigPropertyMapping, new PaymentVoucherHeader, new PaymentVoucherLine] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                if ($model instanceof Property) {
                    $table->boolean('active')->default(true);
                }
                foreach (array_diff($model->getFillable(), ['id', 'created_at', 'updated_at']) as $field) {
                    $table->text($field)->nullable();
                }
                if ($model->usesTimestamps()) {
                    $table->timestamps();
                }
            });
        }
        Schema::create('cb_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('display_name');
            $t->boolean('is_global')->default(true);
        });
        Schema::create('cb_role_user', function (Blueprint $t) {
            $t->integer('role_id');
            $t->integer('user_id');
            $t->string('user_type')->nullable();
        });
        Schema::create('cb_permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('display_name')->nullable();
            $t->text('description')->nullable();
            $t->integer('module_id')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_permission_role', function (Blueprint $t) {
            $t->integer('permission_id');
            $t->integer('role_id');
        });
        Schema::create('cb_permission_user', function (Blueprint $t) {
            $t->integer('permission_id');
            $t->integer('user_id');
            $t->string('user_type');
        });
        Schema::create('cb_modules', function (Blueprint $t) {
            $t->id();
            $t->string('module_name');
        });
        Schema::create('cb_sub_modules', function (Blueprint $t) {
            $t->id();
            $t->string('sub_module_name');
            $t->string('sub_module_url');
            $t->integer('module_id');
            $t->string('icon')->nullable();
            $t->integer('order');
            $t->string('added_by')->nullable();
            $t->timestamp('added_on')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_sub_module_permissions', function (Blueprint $t) {
            $t->id();
            $t->integer('role_id');
            $t->integer('sub_module_id');
            $t->string('added_by')->nullable();
            $t->timestamp('added_on')->nullable();
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('block')->default(false);
        });
        Schema::create('sys_user_staff_links', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('staff_id');
        });
        Schema::create('sys_set_zones', function (Blueprint $t) {
            $t->id();
            $t->integer('zone_manager_id')->nullable();
            $t->integer('zone_supervisor_id')->nullable();
        });
        DB::table('cb_modules')->insert(['id' => 1, 'module_name' => 'Property Actions']);
        foreach ([1 => 'directors', 2 => 'Accountant', 3 => 'Field Officer'] as $id => $role) {
            DB::table('cb_roles')->insert(['id' => $id, 'name' => $role, 'display_name' => $role, 'is_global' => true]);
            DB::table('josya_users')->insert(['id' => $id, 'name' => $role, 'block' => false]);
            DB::table('cb_role_user')->insert(['role_id' => $id, 'user_id' => $id, 'user_type' => User::class]);
        }
        (require database_path('migrations/2026_11_24_100000_create_property_ownership_transfers.php'))->up();
        (new PropertyOwnershipTransferMenuSeeder)->run();
        foreach ([1 => 'Outgoing', 2 => 'Incoming', 3 => 'Third owner'] as $id => $name) {
            DB::table('sys_pm_clients')->insert(['id' => $id, 'names' => $name, 'reference_number' => 'LAN-'.$id, 'current_status_id' => 5, 'mobile_number' => '0700000000', 'client_password' => 'never copy']);
            DB::table('clients_bank_details')->insert(['id' => $id, 'client_id' => $id, 'bank_id' => 1, 'branch_id' => 1, 'account_name' => $name, 'account_number' => '123400'.$id]);
        }
        DB::table('sys_pm_properties')->insert(['id' => 1, 'client_id' => 1, 'property_name' => 'Robert Rentals', 'reference_number' => '1055E', 'current_status_id' => 5, 'active' => true]);
        DB::table('sys_pm_properties')->insert(['id' => 2, 'client_id' => 1, 'property_name' => 'Other property', 'reference_number' => 'OTHER', 'current_status_id' => 5, 'active' => true]);
        DB::table('sys_pm_units')->insert(['id' => 1, 'property_id' => 1, 'unit_code' => '1055E-101', 'occupancy_status_id' => 38, 'current_status_id' => 5]);
        DB::table('sys_pm_service_agreements')->insert(['id' => 1, 'property_id' => 1, 'client_d' => 1, 'current_status_id' => 5, 'date_start' => '2026-01-01', 'date_end' => '2027-01-01', 'commission_percentage' => 10, 'years' => 1, 'vat' => 'Yes', 'vat_per' => 16]);
        DB::table('sys_pm_lease_agreements')->insert(['id' => 1, 'property_id' => 1, 'client_id' => 1, 'sla_id' => 1, 'tenant_id' => 10, 'unit_id' => 1, 'lease_status' => 'Active', 'signed_pdf_path' => 'signed-original.pdf']);
        DB::table('sys_pm_debtors_invoices')->insert(['id' => 1, 'property_id' => 1, 'client_id' => 1, 'sla_id' => 1, 'tenant_id' => 10, 'unit_id' => 1, 'lease_id' => 1, 'current_status_id' => 8, 'returns_period' => '2026-11-01', 'total_amount' => 1000, 'current_balance' => 700, 'reference_number' => 'INV-1']);
        DB::table('sys_pm_deposits')->insert(['id' => 1, 'property_id' => 1, 'client_id' => 1, 'sla_id' => 1, 'tenant_id' => 10, 'unit_id' => 1, 'held_by' => 'Company', 'amount' => 2000]);
        DB::table('cb_sla_invoiceable_items')->insert(['sla_id' => 1, 'item_id' => 4, 'amount' => 500]);
        DB::table('sys_set_invoiceable_items_cycles')->insert(['id' => 1, 'cycle_name' => 'Monthly']);
        DB::table('sys_set_invoiceable_items')->insert(['id' => 4, 'item_name' => 'Service Charge', 'billing_code' => '0037', 'default_cycle_id' => 1, 'advance_payable' => 'Yes']);
        $this->service = app(PropertyOwnershipTransferService::class);
        $this->actingAs($this->user(1));
        $this->mock(ServiceAgreementApprovalService::class, function ($mock) {
            $mock->shouldReceive('approve')->andReturnUsing(function ($ids) {
                ServiceAgreement::whereIn('id', $ids)->update(['current_status_id' => 5]);

                return ['done' => $ids, 'skipped' => []];
            });
        });
    }

    private function user(int $id): User
    {
        return User::findOrFail($id);
    }

    private function transfer(array $data = []): PropertyOwnershipTransfer
    {
        return $this->service->create(array_merge([
            'property_id' => 1, 'to_client_id' => 2, 'bank_detail_id' => 2, 'effective_date' => '2026-12-01',
            'arrears_policy' => 'transfer', 'commission_percentage' => 12, 'duration_years' => 1,
            'reason' => 'Signed sale and ownership instructions', 'documents' => [['path' => 'private/document.pdf', 'name' => 'Transfer.pdf']],
        ], $data), $this->user(1));
    }

    private function approved(array $data = []): PropertyOwnershipTransfer
    {
        $transfer = $this->transfer($data);
        $this->service->review($transfer, $this->user(2), 'All deposits, tenant credits and bank instructions reconciled.');
        $this->service->approve($transfer->fresh(), $this->user(1), 'Signed handover and SLA terms approved.');

        return $transfer->fresh();
    }

    private function rejected(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, implode(' ', $e->validator->errors()->all()));
        }
    }
}

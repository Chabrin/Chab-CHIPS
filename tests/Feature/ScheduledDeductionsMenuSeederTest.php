<?php

namespace Tests\Feature;

use Database\Seeders\ScheduledDeductionsMenuSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Payroll menu gets "Scheduled Deductions" between HELB Loans and Prorated Deductions, for the HR Manager and Directors only.
 */
class ScheduledDeductionsMenuSeederTest extends TestCase
{
    private const PAYROLL_MODULE = 6;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cb_modules', function (Blueprint $t) { $t->id(); $t->string('module_name')->nullable(); });
        Schema::create('cb_sub_modules', function (Blueprint $t) {
            $t->id();
            $t->string('sub_module_name');
            $t->string('sub_module_url');
            $t->string('icon')->nullable();
            $t->integer('order')->nullable();
            $t->string('added_by')->nullable();
            $t->dateTime('added_on')->nullable();
            $t->integer('module_id')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_sub_module_permissions', function (Blueprint $t) {
            $t->integer('role_id');
            $t->integer('sub_module_id');
            $t->string('added_by')->nullable();
            $t->dateTime('added_on')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('cb_permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('display_name')->nullable();
            $t->string('description')->nullable();
            $t->integer('module_id')->nullable();
            $t->timestamps();
        });
        Schema::create('cb_permission_role', function (Blueprint $t) {
            $t->integer('permission_id');
            $t->integer('role_id');
            $t->timestamps();
        });

        DB::table('cb_modules')->insert(['id' => self::PAYROLL_MODULE, 'module_name' => 'Payroll Mgt']);
        foreach ([['Payslips', '/payroll/payslips', 1], ['HELB Loans', '/payroll/helbloans', 6], ['Prorated Deductions', '/payroll/prorated/deductions', 7], ['Tax Bands', '/tax/bands', 8], ['Monthly Payroll Items', '/payroll/monthly-items', 9]] as [$name, $url, $order]) {
            DB::table('cb_sub_modules')->insert(['sub_module_name' => $name, 'sub_module_url' => $url, 'order' => $order, 'module_id' => self::PAYROLL_MODULE]);
        }
        foreach (['hr_manager', 'directors', 'accountant'] as $role) {
            DB::table('cb_roles')->insert(['name' => $role]);
        }
    }

    private function menuOrder(): array
    {
        return DB::table('cb_sub_modules')->where('module_id', self::PAYROLL_MODULE)->orderBy('order')->pluck('sub_module_name')->all();
    }

    private function roleId(string $name): int
    {
        return (int) DB::table('cb_roles')->where('name', $name)->value('id');
    }

    public function test_the_entry_goes_between_helb_loans_and_prorated_deductions_in_the_payroll_module(): void
    {
        $this->seed(ScheduledDeductionsMenuSeeder::class);

        $this->assertSame(['Payslips', 'HELB Loans', 'Scheduled Deductions', 'Prorated Deductions', 'Tax Bands', 'Monthly Payroll Items'], $this->menuOrder());
        $entry = DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/scheduled-deductions')->first();
        $this->assertSame(self::PAYROLL_MODULE, (int) $entry->module_id);
        $this->assertSame(7, (int) $entry->order);
    }

    public function test_the_entry_and_every_permission_go_to_the_hr_manager_and_directors_only(): void
    {
        $this->seed(ScheduledDeductionsMenuSeeder::class);

        $entryId = DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/scheduled-deductions')->value('id');
        $this->assertEqualsCanonicalizing(
            [$this->roleId('hr_manager'), $this->roleId('directors')],
            DB::table('cb_sub_module_permissions')->where('sub_module_id', $entryId)->pluck('role_id')->map(fn ($id) => (int) $id)->all()
        );

        $names = array_keys(ScheduledDeductionsMenuSeeder::PERMISSIONS);
        $this->assertEqualsCanonicalizing([
            'read_scheduled_deductions', 'create_scheduled_deductions', 'update_scheduled_deductions',
            'close_scheduled_deductions', 'manage_deduction_types', 'override_deduction_limits',
        ], $names);
        foreach (['hr_manager', 'directors'] as $role) {
            $granted = DB::table('cb_permission_role')
                ->join('cb_permissions', 'cb_permissions.id', '=', 'cb_permission_role.permission_id')
                ->where('cb_permission_role.role_id', $this->roleId($role))
                ->pluck('cb_permissions.name')->all();
            $this->assertEqualsCanonicalizing($names, $granted, $role);
        }
        $this->assertSame(0, DB::table('cb_permission_role')->where('role_id', $this->roleId('accountant'))->count());
        $this->assertSame(0, DB::table('cb_sub_module_permissions')->where('role_id', $this->roleId('accountant'))->count());
        $this->assertSame(6, DB::table('cb_permissions')->whereIn('name', $names)->where('module_id', self::PAYROLL_MODULE)->count(), 'they sit under the Payroll module on the roles screen');
    }

    public function test_running_it_again_adds_nothing_and_does_not_move_the_menu_again(): void
    {
        $this->seed(ScheduledDeductionsMenuSeeder::class);
        $orders = DB::table('cb_sub_modules')->orderBy('id')->pluck('order', 'sub_module_name')->all();
        $grants = DB::table('cb_permission_role')->count();

        $this->seed(ScheduledDeductionsMenuSeeder::class);

        $this->assertSame($orders, DB::table('cb_sub_modules')->orderBy('id')->pluck('order', 'sub_module_name')->all());
        $this->assertSame(1, DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/scheduled-deductions')->count());
        $this->assertSame($grants, DB::table('cb_permission_role')->count());
        $this->assertSame(6, DB::table('cb_permissions')->count());
    }

    public function test_a_role_that_does_not_exist_is_skipped_without_stopping_the_rest(): void
    {
        DB::table('cb_roles')->where('name', 'hr_manager')->delete();

        $this->seed(ScheduledDeductionsMenuSeeder::class);

        $entryId = DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/scheduled-deductions')->value('id');
        $this->assertSame([$this->roleId('directors')], DB::table('cb_sub_module_permissions')->where('sub_module_id', $entryId)->pluck('role_id')->map(fn ($id) => (int) $id)->all());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractChange;
use App\Models\User;
use App\Services\StaffHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The History tab of a staff profile and of a contract: who changed what, when, what it was and what it is now. Read only.
 */
class StaffHistoryTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const STAFF = 5;
    private const OTHER = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher(); // the changes are written by hand below, not by the observers

        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); });
        Schema::create('sys_staff', function (Blueprint $t) { $t->id(); $t->string('full_names')->nullable(); });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) { $t->id(); $t->string('position_name')->nullable(); });
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id');
            $t->decimal('basic_salary', 12, 2)->nullable();
        });
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) { $t->id(); $t->string('element_name')->nullable(); });
        Schema::create('contract_changes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->unsignedBigInteger('staff_id')->nullable();
            $t->string('field');
            $t->unsignedBigInteger('element_id')->nullable();
            $t->string('old_value')->nullable();
            $t->string('new_value')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('cb_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('table_name');
            $t->unsignedBigInteger('record_id')->nullable();
            $t->string('operation');
            $t->text('old_data')->nullable();
            $t->text('new_data')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('contract_id')->nullable();
            $t->integer('document_type_id')->default(1);
            $t->string('title')->nullable();
            $t->string('file_path')->default('x');
            $t->string('file_name')->nullable();
            $t->integer('file_size')->nullable();
            $t->string('mime_type')->nullable();
            $t->integer('uploaded_by')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_18_110000_create_employee_document_removals_table.php'))->up();

        DB::table('josya_users')->insert([['id' => 1, 'name' => 'Hannah HR'], ['id' => 2, 'name' => 'Daniel Director']]);
        DB::table('sys_staff')->insert([['id' => self::STAFF, 'full_names' => 'MARY WANJIKU'], ['id' => self::OTHER, 'full_names' => 'JOHN OTIENO']]);
        DB::table('sys_set_staff_positions')->insert([['id' => 1, 'position_name' => 'Accountant'], ['id' => 2, 'position_name' => 'Senior Accountant']]);
        $this->actAs(['read_staff', 'read_contracts']);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actAs(array $permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->id = 1;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function audit(string $table, int $recordId, string $operation, ?array $old, ?array $new, int $by = 1, string $at = '2026-11-10 09:00:00'): void
    {
        // Written straight to the table: the audit log's date is set by the system, so the model will not take one
        DB::table('cb_audit_logs')->insert([
            'table_name' => $table, 'record_id' => $recordId, 'operation' => $operation,
            'old_data' => $old === null ? null : json_encode($old), 'new_data' => $new === null ? null : json_encode($new),
            'user_id' => $by, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function contract(int $staff = self::STAFF): int
    {
        return DB::table('sys_hr_employee_contracts')->insertGetId(['staff_id' => $staff, 'basic_salary' => 50000]);
    }

    private function history(): StaffHistory
    {
        return app(StaffHistory::class);
    }

    private function find(array $rows, string $what): ?array
    {
        foreach ($rows as $row) {
            if ($row['what'] === $what) {
                return $row;
            }
        }

        return null;
    }

    // ---- the staff record -------------------------------------------------------------------------------------------------

    public function test_each_changed_field_is_a_row_with_who_when_was_and_now(): void
    {
        $this->audit('sys_staff', self::STAFF, 'update', ['position_id' => 1, 'current_status_id' => 1], ['position_id' => 2, 'current_status_id' => 3], 2, '2026-11-12 14:30:00');

        $result = $this->history()->forStaff(self::STAFF);

        $position = $this->find($result['rows'], 'Position');
        $this->assertSame('Daniel Director', $position['by']);
        $this->assertSame('12 Nov 2026, 14:30', $position['at']->format('d M Y, H:i'));
        $this->assertSame('Accountant', $position['old']);
        $this->assertSame('Senior Accountant', $position['new']);
        $status = $this->find($result['rows'], 'Status');
        $this->assertSame(['Active', 'Terminated'], [$status['old'], $status['new']]);
        $this->assertSame('Staff record', $status['area']);
    }

    public function test_id_numbers_pins_and_bank_accounts_show_only_the_last_four(): void
    {
        $this->audit('sys_staff', self::STAFF, 'update',
            ['national_id' => '12345678', 'pin_number' => 'A001234567Z', 'account_number' => '0011223344'],
            ['national_id' => '87654321', 'pin_number' => 'A007654321Y', 'account_number' => '0055667788']);

        $rows = $this->history()->forStaff(self::STAFF)['rows'];

        $this->assertSame(['****5678', '****4321'], [$this->find($rows, 'National ID')['old'], $this->find($rows, 'National ID')['new']]);
        $this->assertSame('****567Z', $this->find($rows, 'KRA PIN')['old']);
        $this->assertSame(['****3344', '****7788'], [$this->find($rows, 'Bank account number')['old'], $this->find($rows, 'Bank account number')['new']]);
        $this->assertStringNotContainsString('12345678', json_encode($rows));
        $this->assertStringNotContainsString('0055667788', json_encode($rows));
    }

    public function test_a_new_record_a_deleted_record_and_plumbing_fields(): void
    {
        $this->audit('sys_staff', self::STAFF, 'create', null, ['full_names' => 'MARY WANJIKU'], 1, '2026-11-01 08:00:00');
        $this->audit('sys_staff', self::STAFF, 'update', ['date_time' => 'a', 'full_names' => 'MARY W'], ['date_time' => 'b', 'full_names' => 'MARY WANJIKU'], 1, '2026-11-02 08:00:00');

        $rows = $this->history()->forStaff(self::STAFF)['rows'];

        $this->assertNotNull($this->find($rows, 'Staff record created'));
        $this->assertNull($this->find($rows, 'Date time'), 'the record\'s own bookkeeping is not a change a person made');
        $this->assertSame(['MARY W', 'MARY WANJIKU'], [$this->find($rows, 'Full names')['old'], $this->find($rows, 'Full names')['new']]);
    }

    public function test_a_change_with_no_old_value_says_empty(): void
    {
        $this->audit('sys_staff', self::STAFF, 'update', ['mobile_number' => null], ['mobile_number' => '+254712345678']);

        $row = $this->find($this->history()->forStaff(self::STAFF)['rows'], 'Mobile number');

        $this->assertSame(['empty', '+254712345678'], [$row['old'], $row['new']]);
    }

    public function test_newest_comes_first_and_only_this_staff_members_changes_are_listed(): void
    {
        $this->audit('sys_staff', self::STAFF, 'update', ['address' => 'old'], ['address' => 'first'], 1, '2026-11-01 08:00:00');
        $this->audit('sys_staff', self::STAFF, 'update', ['address' => 'first'], ['address' => 'second'], 1, '2026-11-05 08:00:00');
        $this->audit('sys_staff', self::OTHER, 'update', ['address' => 'x'], ['address' => 'someone else'], 1, '2026-11-09 08:00:00');

        $rows = $this->history()->forStaff(self::STAFF)['rows'];

        $this->assertSame(['second', 'first'], array_column($rows, 'new'));
    }

    // ---- contracts --------------------------------------------------------------------------------------------------------

    public function test_a_salary_change_is_listed_once_even_though_two_logs_recorded_it(): void
    {
        $contract = $this->contract();
        ContractChange::create(['contract_id' => $contract, 'staff_id' => self::STAFF, 'field' => ContractChange::BASIC_SALARY, 'old_value' => '50000.00', 'new_value' => '60000.00', 'changed_by' => 2, 'created_at' => '2026-11-12 10:00:00']);
        $this->audit('sys_hr_employee_contracts', $contract, 'update', ['basic_salary' => 50000], ['basic_salary' => 60000], 2, '2026-11-12 10:00:00');

        $rows = array_values(array_filter($this->history()->forStaff(self::STAFF)['rows'], fn ($r) => $r['what'] === 'Basic salary'));

        $this->assertCount(1, $rows);
        $this->assertSame(['KES 50,000.00', 'KES 60,000.00', 'Daniel Director'], [$rows[0]['old'], $rows[0]['new'], $rows[0]['by']]);
    }

    public function test_a_salary_change_only_the_audit_log_caught_is_still_shown(): void
    {
        $contract = $this->contract();
        $this->audit('sys_hr_employee_contracts', $contract, 'update', ['basic_salary' => 50000], ['basic_salary' => 55000]);

        $row = $this->find($this->history()->forStaff(self::STAFF)['rows'], 'Basic salary');

        $this->assertSame(['KES 50,000.00', 'KES 55,000.00', 'Contract'], [$row['old'], $row['new'], $row['area']]);
    }

    public function test_changes_to_earnings_and_deductions_on_a_contract_are_described_by_name(): void
    {
        $contract = $this->contract();
        DB::table('sys_hr_payroll_elements')->insert(['id' => 9, 'element_name' => 'House Allowance']);
        ContractChange::create(['contract_id' => $contract, 'staff_id' => self::STAFF, 'field' => ContractChange::ELEMENT_ADDED, 'element_id' => 9, 'new_value' => '8000', 'changed_by' => 1, 'created_at' => '2026-11-11 10:00:00']);

        $rows = $this->history()->forStaff(self::STAFF)['rows'];

        $this->assertNotNull($this->find($rows, 'House Allowance added at 8,000.00'));
    }

    public function test_a_contract_created_and_a_contract_deleted_are_both_on_record(): void
    {
        $contract = $this->contract();
        ContractChange::contractCreated(Contract::find($contract), 1);
        $this->audit('sys_hr_employee_contracts', 99, 'delete', ['staff_id' => self::STAFF, 'basic_salary' => 40000], null, 2, '2026-11-15 12:00:00');
        $this->audit('sys_hr_employee_contracts', 98, 'delete', ['staff_id' => self::OTHER, 'basic_salary' => 40000], null, 2, '2026-11-15 12:30:00');

        $rows = $this->history()->forStaff(self::STAFF)['rows'];

        $created = $this->find($rows, 'Contract created');
        $this->assertSame('Basic salary KES 50,000.00', $created['new']);
        $this->assertCount(1, array_filter($rows, fn ($r) => $r['what'] === 'Contract deleted'), 'only this staff member\'s deleted contract');
    }

    public function test_a_contracts_own_history_has_no_staff_record_rows(): void
    {
        $contract = $this->contract();
        $this->audit('sys_staff', self::STAFF, 'update', ['address' => 'a'], ['address' => 'b']);
        $this->audit('sys_hr_employee_contracts', $contract, 'update', ['basic_salary' => 50000], ['basic_salary' => 52000]);

        $rows = $this->history()->forContract($contract)['rows'];

        $this->assertSame(['Basic salary'], array_column($rows, 'what'));
    }

    // ---- documents --------------------------------------------------------------------------------------------------------

    public function test_documents_added_and_removed_are_listed_with_who_and_when(): void
    {
        $id = DB::table('employee_documents')->insertGetId(['employee_id' => self::STAFF, 'title' => 'Signed Employment Contract', 'file_name' => 'signed.pdf', 'uploaded_by' => 1]);
        $this->audit('employee_documents', $id, 'create', null, ['employee_id' => self::STAFF, 'title' => 'Signed Employment Contract'], 1, '2026-11-03 09:00:00');
        DB::table('employee_document_removals')->insert([
            'original_id' => 77, 'employee_id' => self::STAFF, 'title' => 'Signed Employment Contract', 'file_path' => 'p.pdf', 'file_name' => 'wrong.pdf',
            'uploaded_by' => 1, 'removed_by' => 2, 'removed_at' => '2026-11-04 09:00:00', 'reason' => 'Removed',
        ]);
        DB::table('employee_document_removals')->insert([
            'original_id' => 78, 'employee_id' => self::STAFF, 'title' => 'CV', 'file_path' => 'q.pdf', 'file_name' => 'old-cv.pdf',
            'uploaded_by' => 1, 'removed_by' => 1, 'removed_at' => '2026-11-05 09:00:00', 'reason' => 'Replaced by a newer file',
        ]);

        $rows = $this->history()->forStaff(self::STAFF)['rows'];

        $this->assertNotNull($this->find($rows, 'Document added: Signed Employment Contract'));
        $removed = $this->find($rows, 'Document removed: Signed Employment Contract');
        $this->assertSame(['Daniel Director', 'wrong.pdf', 'Document'], [$removed['by'], $removed['old'], $removed['area']]);
        $this->assertNotNull($this->find($rows, 'Document replaced: CV'));
    }

    public function test_a_history_with_no_history_table_yet_still_works(): void
    {
        Schema::drop('employee_document_removals');
        $this->audit('sys_staff', self::STAFF, 'update', ['address' => 'a'], ['address' => 'b']);

        $this->assertCount(1, $this->history()->forStaff(self::STAFF)['rows']);
    }

    // ---- size -------------------------------------------------------------------------------------------------------------

    public function test_only_the_latest_rows_are_shown_and_the_total_says_how_many_there_are(): void
    {
        for ($i = 1; $i <= StaffHistory::LIMIT + 5; $i++) {
            $this->audit('sys_staff', self::STAFF, 'update', ['address' => 'a' . ($i - 1)], ['address' => 'a' . $i], 1, Carbon::parse('2026-01-01')->addMinutes($i)->toDateTimeString());
        }

        $result = $this->history()->forStaff(self::STAFF);

        $this->assertSame(StaffHistory::LIMIT, count($result['rows']));
        $this->assertSame(StaffHistory::LIMIT + 5, $result['total']);
        $this->assertSame('a' . (StaffHistory::LIMIT + 5), $result['rows'][0]['new'], 'the newest is first');
    }

    // ---- the pages --------------------------------------------------------------------------------------------------------

    public function test_the_staff_history_page_part_shows_the_rows_and_says_it_cannot_be_edited(): void
    {
        $this->audit('sys_staff', self::STAFF, 'update', ['position_id' => 1], ['position_id' => 2], 2);

        $response = $this->get(route('staff.history', Crypt::encrypt(self::STAFF)))->assertOk();

        $response->assertSee('Position')->assertSee('Accountant')->assertSee('Senior Accountant')->assertSee('Daniel Director')
            ->assertSee('cannot be edited or deleted');
    }

    public function test_an_empty_history_says_so(): void
    {
        $this->get(route('staff.history', Crypt::encrypt(self::OTHER)))->assertOk()->assertSee('Nothing has been changed');
    }

    public function test_the_staff_history_needs_permission_and_a_real_address(): void
    {
        $this->actAs(['read_contracts']);
        $this->get(route('staff.history', Crypt::encrypt(self::STAFF)))->assertForbidden();

        $this->actAs(['read_staff']);
        $this->get(route('staff.history', 'not-a-real-token'))->assertNotFound();
    }

    public function test_the_contract_history_part_needs_a_contract_and_permission(): void
    {
        $contract = $this->contract();
        $this->audit('sys_hr_employee_contracts', $contract, 'update', ['basic_salary' => 50000], ['basic_salary' => 61000]);

        $this->get(route('contract.history', $contract))->assertOk()->assertSee('KES 61,000.00');
        $this->get(route('contract.history', 9999))->assertNotFound();

        $this->actAs(['update_staff']);
        $this->get(route('contract.history', $contract))->assertForbidden();
    }

    public function test_the_history_can_only_be_read_never_changed(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_ends_with((string) $route->getName(), '.history') && in_array($route->getName(), ['staff.history', 'contract.history'], true)) {
                $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->getName());
            }
        }
        $this->assertNotNull(Route::getRoutes()->getByName('staff.history'));
        $this->assertNotNull(Route::getRoutes()->getByName('contract.history'));
    }

    public function test_the_profile_has_a_history_tab_and_the_contract_page_a_history_section(): void
    {
        $details = file_get_contents(resource_path('views/staff/details.blade.php'));
        $this->assertStringContainsString('id="history-tab"', $details);
        $this->assertStringContainsString("route('staff.history'", $details);

        $contract = new Contract();
        $contract->forceFill(['id' => 3, 'staff_id' => 5, 'basic_salary' => 50000]);
        $contract->setRelation('signedDocument', null);
        $edit = view('staff.contracts.contracts_edit', [
            'contract' => $contract, 'visibleElements' => collect(), 'hiddenElements' => collect(), 'staffCopy' => null, 'exclusions' => [],
        ])->render();
        $this->assertStringContainsString('id="historyPanel" data-url="' . route('contract.history', 3) . '"', $edit);
    }
}

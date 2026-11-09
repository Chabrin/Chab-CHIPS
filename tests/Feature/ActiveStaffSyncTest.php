<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Services\ActiveStaffSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Updating the staff list from the legacy "active staff" export: who is matched, which values overwrite, what
 * happens to people who are not on the list, and that a preview writes nothing.
 */
class ActiveStaffSyncTest extends TestCase
{
    private const FIELD_OFFICERS = 1;
    private const ZONE_MANAGERS = 2;
    private const PROPERTY_DEPT = 5;
    private const BRANCH_ROW = 7;
    private const EQUITY = 43;

    private string $storageBackup;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->dateTime('date_time')->nullable();
            foreach (['staff_number', 'full_names', 'gender', 'marital', 'national_id', 'district', 'location', 'sub_location', 'spouse_name', 'birth_place', 'pin_number', 'nhif_number', 'nssf_number', 'account_number', 'mobile_number', 'email_address', 'personal_email', 'disability', 'citizenship', 'hire_date'] as $column) {
                $t->string($column)->nullable();
            }
            $t->date('birth_date')->nullable();
            foreach (['bank_name', 'branch_id', 'position_id', 'department_id'] as $column) {
                $t->integer($column)->nullable();
            }
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) {
            $t->id();
            $t->string('position_name');
            $t->integer('department_id')->nullable();
        });
        Schema::create('sys_set_banks_branches', function (Blueprint $t) {
            $t->id();
            $t->integer('bank_id');
            $t->string('branch_code');
        });

        DB::table('sys_set_staff_positions')->insert([
            ['id' => self::FIELD_OFFICERS, 'position_name' => 'Field Officers', 'department_id' => self::PROPERTY_DEPT],
            ['id' => self::ZONE_MANAGERS, 'position_name' => 'Zone Managers', 'department_id' => self::PROPERTY_DEPT],
            ['id' => 3, 'position_name' => 'QA & Valuation Manager', 'department_id' => 2],
            ['id' => 4, 'position_name' => 'Unplaced Position', 'department_id' => null],
        ]);
        DB::table('sys_set_banks_branches')->insert(['id' => self::BRANCH_ROW, 'bank_id' => self::EQUITY, 'branch_code' => '03033']);

        $this->storageBackup = $this->app->storagePath();
        File::ensureDirectoryExists(sys_get_temp_dir() . '/active-staff-sync-test');
        $this->app->useStoragePath(sys_get_temp_dir() . '/active-staff-sync-test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(sys_get_temp_dir() . '/active-staff-sync-test');
        $this->app->useStoragePath($this->storageBackup);

        parent::tearDown();
    }

    private function staff(string $number, array $attributes = []): int
    {
        return DB::table('sys_staff')->insertGetId($attributes + ['staff_number' => $number, 'full_names' => 'EXISTING NAME', 'current_status_id' => 1]);
    }

    private function row(string $number, array $cells = []): array
    {
        return $cells + ['EMPNUMB' => $number, 'FULLNAME' => 'FILE NAME', 'CURRSTAT' => 'Active'];
    }

    private function sync(array $rows, bool $apply = true): array
    {
        return app(ActiveStaffSync::class)->run($rows, $apply);
    }

    private function find(int $id): object
    {
        return DB::table('sys_staff')->find($id);
    }

    public function test_staff_are_matched_by_number_ignoring_leading_zeros(): void
    {
        $id = $this->staff('002');

        $report = $this->sync([$this->row('2', ['FULLNAME' => 'JANE A EXAMPLE'])]);

        $this->assertSame(1, $report['matched']);
        $this->assertSame('JANE A EXAMPLE', $this->find($id)->full_names);
        $this->assertSame([], $report['created']);
    }

    public function test_a_value_in_the_file_replaces_the_systems_value_but_blanks_never_erase(): void
    {
        $id = $this->staff('19', ['national_id' => '11111111', 'pin_number' => 'A111111111B', 'district' => 'OLD DISTRICT', 'nssf_number' => '123456789', 'spouse_name' => 'OLD SPOUSE']);

        $this->sync([$this->row('19', ['NATIDNO' => '22222222', 'NUMPIN' => 'NULL', 'DISTRCT' => 'NULL', 'SPOUSNAM' => 'NIL', 'NUMNSSF' => null])]);

        $staff = $this->find($id);
        $this->assertSame('22222222', $staff->national_id);
        $this->assertSame('A111111111B', $staff->pin_number, 'NULL in the file keeps the existing value');
        $this->assertSame('OLD DISTRICT', $staff->district, 'the text NULL is not a district');
        $this->assertSame('OLD SPOUSE', $staff->spouse_name, 'NIL means none, not a name');
        $this->assertSame('123456789', $staff->nssf_number);
    }

    public function test_values_are_tidied_and_unusable_ones_are_kept_out_and_reported(): void
    {
        $id = $this->staff('19', ['marital' => 'Single', 'pin_number' => 'A111111111B', 'gender' => 'Male']);

        $report = $this->sync([$this->row('19', ['MSTATUS' => 'Active', 'NUMPIN' => 'A', 'GENDER' => 'FEMALE', 'PWDSTAT' => 'Yes', 'CITIZEN' => 'RESIDENT', 'MOBNUM1' => '+254 700-000 001', 'WEMAIL' => 'not an email'])]);

        $staff = $this->find($id);
        $this->assertSame('Single', $staff->marital);
        $this->assertSame('A111111111B', $staff->pin_number);
        $this->assertSame('Female', $staff->gender);
        $this->assertSame('YES', $staff->disability);
        $this->assertSame('Resident', $staff->citizenship);
        $this->assertSame('+254700000001', $staff->mobile_number);
        $this->assertNull($staff->email_address);
        $this->assertEqualsCanonicalizing(['MSTATUS', 'NUMPIN', 'WEMAIL'], array_column($report['skipped'], 1));
    }

    public function test_staff_not_on_the_list_become_terminated_only_when_they_are_active(): void
    {
        $this->staff('1');
        $active = $this->staff('50');
        $suspended = $this->staff('51', ['current_status_id' => 2]);
        $onLeave = $this->staff('52', ['current_status_id' => 4]);
        $terminated = $this->staff('53', ['current_status_id' => 3]);

        $report = $this->sync([$this->row('1')]);

        $this->assertSame(3, $this->find($active)->current_status_id);
        $this->assertSame(2, $this->find($suspended)->current_status_id);
        $this->assertSame(4, $this->find($onLeave)->current_status_id);
        $this->assertSame(3, $this->find($terminated)->current_status_id);
        $this->assertSame(['50'], $report['made_inactive']);
        $this->assertSame(['2' => 1, '4' => 1, '3' => 1], $report['left_alone']);
    }

    public function test_staff_with_no_staff_number_are_left_alone_because_they_cannot_be_checked_against_the_list(): void
    {
        $this->staff('1');
        $blank = DB::table('sys_staff')->insertGetId(['staff_number' => '', 'full_names' => 'NO NUMBER', 'current_status_id' => 1]);
        $missing = DB::table('sys_staff')->insertGetId(['staff_number' => null, 'full_names' => 'NULL NUMBER', 'current_status_id' => 1]);

        $report = $this->sync([$this->row('1')]);

        $this->assertSame(1, $this->find($blank)->current_status_id);
        $this->assertSame(1, $this->find($missing)->current_status_id);
        $this->assertEqualsCanonicalizing([$blank, $missing], $report['no_number']);
        $this->assertSame([], $report['made_inactive']);
    }

    public function test_a_terminated_person_on_the_list_is_active_again_but_suspended_and_on_leave_stay(): void
    {
        $back = $this->staff('10', ['current_status_id' => 3]);
        $suspended = $this->staff('11', ['current_status_id' => 2]);
        $onLeave = $this->staff('12', ['current_status_id' => 4]);

        $report = $this->sync([$this->row('10'), $this->row('11'), $this->row('12')]);

        $this->assertSame(1, $this->find($back)->current_status_id);
        $this->assertSame(2, $this->find($suspended)->current_status_id);
        $this->assertSame(4, $this->find($onLeave)->current_status_id);
        $this->assertSame(['10'], $report['reactivated']);
    }

    public function test_people_on_the_list_who_are_not_in_the_system_are_added_as_active_staff(): void
    {
        $report = $this->sync([$this->row('344', ['FULLNAME' => 'JOHN T SAMPLE', 'NATIDNO' => '44444444', 'RTNCAT' => 'FIELD OFFICER'])]);

        $this->assertSame(['344'], $report['created']);
        $new = DB::table('sys_staff')->where('staff_number', '344')->first();
        $this->assertSame(1, $new->current_status_id);
        $this->assertSame('JOHN T SAMPLE', $new->full_names);
        $this->assertSame('44444444', $new->national_id);
        $this->assertSame(self::FIELD_OFFICERS, $new->position_id);
        $this->assertSame(self::PROPERTY_DEPT, $new->department_id);
    }

    public function test_someone_in_the_file_with_no_name_is_not_added(): void
    {
        $report = $this->sync([['EMPNUMB' => '999', 'FULLNAME' => 'NULL']]);

        $this->assertSame([], $report['created']);
        $this->assertSame(0, DB::table('sys_staff')->count());
        $this->assertSame('999', $report['skipped'][0][0]);
    }

    public function test_position_and_department_follow_the_job_title_whatever_its_case_or_plural(): void
    {
        $a = $this->staff('1', ['position_id' => 4, 'department_id' => 9]);
        $b = $this->staff('2');
        $c = $this->staff('3');

        $this->sync([
            $this->row('1', ['RTNCAT' => 'ZONE MANAGER']),
            $this->row('2', ['RTNCAT' => 'field officer']),
            $this->row('3', ['RTNCAT' => 'QUALITY ASSURANCE & VALUATION MANAGER']),
        ]);

        $this->assertSame([self::ZONE_MANAGERS, self::PROPERTY_DEPT], [$this->find($a)->position_id, $this->find($a)->department_id]);
        $this->assertSame(self::FIELD_OFFICERS, $this->find($b)->position_id);
        $this->assertSame([3, 2], [$this->find($c)->position_id, $this->find($c)->department_id]);
    }

    public function test_an_unknown_job_title_keeps_the_position_and_is_reported(): void
    {
        $id = $this->staff('1', ['position_id' => self::FIELD_OFFICERS, 'department_id' => self::PROPERTY_DEPT]);

        $report = $this->sync([$this->row('1', ['RTNCAT' => 'CHIEF OF EVERYTHING'])]);

        $this->assertSame(self::FIELD_OFFICERS, $this->find($id)->position_id);
        $this->assertSame(['CHIEF OF EVERYTHING' => 1], $report['unknown_titles']);
    }

    public function test_a_position_with_no_department_changes_the_position_but_not_the_department(): void
    {
        $id = $this->staff('1', ['department_id' => 9]);

        $this->sync([$this->row('1', ['RTNCAT' => 'Unplaced Position'])]);

        $this->assertSame([4, 9], [$this->find($id)->position_id, $this->find($id)->department_id]);
    }

    public function test_the_bank_comes_from_the_branch_code_even_when_a_leading_zero_was_lost(): void
    {
        $id = $this->staff('1');
        $other = $this->staff('2', ['bank_name' => 10, 'branch_id' => 3, 'account_number' => '111111']);

        $report = $this->sync([
            $this->row('1', ['BANKBRAN' => '3033', 'BANKACC' => '0110000000001']),
            $this->row('2', ['BANKBRAN' => '99999', 'BANKACC' => '0220000000002']),
        ]);

        $this->assertSame([self::EQUITY, self::BRANCH_ROW, '0110000000001'], [$this->find($id)->bank_name, $this->find($id)->branch_id, $this->find($id)->account_number]);
        // An unknown branch keeps the bank, but the account number from the same row is still a valid number
        $this->assertSame([10, 3], [$this->find($other)->bank_name, $this->find($other)->branch_id]);
        $this->assertContains('BANKBRAN', array_column($report['skipped'], 1));
    }

    public function test_hire_dates_use_the_systems_day_first_reading_and_a_same_date_in_another_format_is_no_change(): void
    {
        $same = $this->staff('1', ['hire_date' => 'Mar  9 2012 12:00AM']);
        $different = $this->staff('2', ['hire_date' => '01/02/2020']);
        $none = $this->staff('3');

        $report = $this->sync([
            $this->row('1', ['APPDATE' => '2012-03-09']),
            $this->row('2', ['APPDATE' => '05/03/2024']),
            $this->row('3', ['APPDATE' => '12/01/2026']),
        ]);

        $this->assertSame('Mar  9 2012 12:00AM', $this->find($same)->hire_date, 'the same day written differently is left as it is');
        $this->assertSame('2024-03-05', $this->find($different)->hire_date);
        $this->assertSame('2026-01-12', $this->find($none)->hire_date, 'day-first, and 1 December 2026 would be in the future anyway');
        $this->assertSame(2, $report['field_counts']['hire_date']);
    }

    public function test_a_preview_writes_nothing_but_reports_exactly_what_applying_does(): void
    {
        $this->staff('1', ['national_id' => '11111111']);
        $this->staff('50');
        $rows = [$this->row('1', ['NATIDNO' => '22222222']), $this->row('344')];
        $before = DB::table('sys_staff')->get()->toJson();

        $preview = $this->sync($rows, false);

        $this->assertSame($before, DB::table('sys_staff')->get()->toJson());
        $applied = $this->sync($rows, true);
        unset($preview['applied'], $applied['applied']);
        $this->assertSame($preview, $applied);
        $this->assertSame(3, DB::table('sys_staff')->count());
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $this->staff('1', ['national_id' => '11111111']);
        $this->staff('50');
        $rows = [$this->row('1', ['NATIDNO' => '22222222', 'RTNCAT' => 'FIELD OFFICER']), $this->row('344')];

        $this->sync($rows);
        $second = $this->sync($rows);

        $this->assertSame(0, $second['staff_changed']);
        $this->assertSame([], $second['created'], '344 exists after the first run');
        $this->assertSame([], $second['made_inactive']);
    }

    public function test_a_number_shared_by_two_staff_records_is_skipped_and_neither_is_terminated(): void
    {
        $one = $this->staff('7', ['national_id' => '11111111']);
        $two = $this->staff('007', ['national_id' => '22222222']);

        $report = $this->sync([$this->row('7', ['NATIDNO' => '33333333'])]);

        $this->assertSame(['7'], $report['ambiguous']);
        $this->assertSame('11111111', $this->find($one)->national_id);
        $this->assertSame(1, $this->find($two)->current_status_id);
        $this->assertSame([], $report['made_inactive']);
    }

    public function test_a_person_listed_twice_in_the_file_is_only_processed_once(): void
    {
        $this->staff('1');

        $report = $this->sync([$this->row('1', ['NATIDNO' => '11111111']), $this->row('001', ['NATIDNO' => '99999999'])]);

        $this->assertSame(['001'], $report['duplicate_in_file']);
        $this->assertSame('11111111', DB::table('sys_staff')->value('national_id'));
    }

    public function test_passwords_and_legacy_codes_in_the_file_are_never_stored(): void
    {
        $this->staff('1');

        $this->sync([$this->row('1', ['PRMSPAS' => '098f6bcd4621d373cade4e832627b4f6', 'EMPTYPE' => '00002', 'EMPADDR' => 'secret@example.com', 'CHANPAS' => '0'])]);

        $this->assertStringNotContainsString('098f6bcd4621d373cade4e832627b4f6', DB::table('sys_staff')->get()->toJson());
        $this->assertNull(DB::table('sys_staff')->value('email_address'));
    }

    // ── the command ───────────────────────────────────────────────────

    private function spreadsheet(array $rows): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $header = array_keys($rows[0]);
        foreach ($header as $i => $name) {
            $sheet->setCellValueExplicit([$i + 1, 1], $name, DataType::TYPE_STRING);
        }
        foreach ($rows as $r => $row) {
            foreach (array_values($row) as $i => $value) {
                $sheet->setCellValueExplicit([$i + 1, $r + 2], (string) $value, DataType::TYPE_STRING);
            }
        }
        $path = sys_get_temp_dir() . '/active-staff-sync-test/list.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }

    public function test_the_command_previews_by_default_and_only_writes_with_apply(): void
    {
        $id = $this->staff('2', ['national_id' => '11111111']);
        $this->staff('50');
        $file = $this->spreadsheet([['EMPNUMB' => '002', 'FULLNAME' => 'FILE NAME', 'NATIDNO' => '22222222', 'BANKBRAN' => '03033']]);

        $this->artisan('app:sync-active-staff', ['file' => $file])
            ->expectsOutputToContain('Preview')
            ->expectsOutputToContain('Nothing was changed')
            ->assertExitCode(0);
        $this->assertSame('11111111', $this->find($id)->national_id);

        $this->artisan('app:sync-active-staff', ['file' => $file, '--apply' => true, '--force' => true])->assertExitCode(0);
        $this->assertSame('22222222', $this->find($id)->national_id);
        $this->assertSame(self::EQUITY, $this->find($id)->bank_name, 'the branch code 03033 kept its zero when read from the sheet');
        $this->assertSame(3, DB::table('sys_staff')->where('staff_number', '50')->value('current_status_id'));
    }

    public function test_the_command_refuses_a_file_without_the_expected_columns_and_changes_nothing(): void
    {
        $this->staff('50');
        $file = $this->spreadsheet([['NAME' => 'someone', 'AGE' => '30']]);

        $this->artisan('app:sync-active-staff', ['file' => $file, '--apply' => true, '--force' => true])->assertExitCode(1);

        $this->assertSame(1, DB::table('sys_staff')->value('current_status_id'));
    }

    public function test_the_report_file_lists_the_changes_and_stays_out_of_the_console(): void
    {
        $this->staff('2', ['national_id' => '11111111']);
        $file = $this->spreadsheet([['EMPNUMB' => '002', 'FULLNAME' => 'FILE NAME', 'NATIDNO' => '22222222']]);

        $this->artisan('app:sync-active-staff', ['file' => $file])->doesntExpectOutputToContain('22222222')->assertExitCode(0);

        $report = File::get(File::glob(sys_get_temp_dir() . '/active-staff-sync-test/app/staff-sync/*-preview.csv')[0]);
        $this->assertStringContainsString('national_id', $report);
        $this->assertStringContainsString('22222222', $report);
    }
}

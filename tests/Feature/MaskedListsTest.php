<?php

namespace Tests\Feature;

use App\Http\Controllers\HrReportsController;
use App\Models\Staff;
use App\Support\MaskedNumber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ID numbers, KRA PINs and bank accounts show only their last four characters in lists. Only the screen changes: the saved
 * number is untouched and everything that needs it still reads it in full.
 */
class MaskedListsTest extends TestCase
{
    public function test_only_the_last_four_characters_show(): void
    {
        $this->assertSame('****5678', MaskedNumber::tail('12345678'));
        $this->assertSame('****567Z', MaskedNumber::tail('A001234567Z'));
        $this->assertSame('****3344', MaskedNumber::tail('0011 2233 44'), 'spaces are not part of the number');
        $this->assertSame('****2345', MaskedNumber::tail('1234567890123456789012345'));
    }

    public function test_a_number_no_longer_than_what_stays_showing_is_hidden_completely(): void
    {
        $this->assertSame('****', MaskedNumber::tail('1234'));
        $this->assertSame('****', MaskedNumber::tail('12'));
    }

    public function test_nothing_stays_nothing(): void
    {
        $this->assertSame('', MaskedNumber::tail(null));
        $this->assertSame('', MaskedNumber::tail('   '));
        $this->assertSame('—', MaskedNumber::orDash(null));
        $this->assertSame('-', MaskedNumber::orDash('', '-'));
        $this->assertSame('****5678', MaskedNumber::orDash('12345678'));
    }

    public function test_the_staff_directory_report_shows_the_last_four_and_the_saved_number_is_untouched(): void
    {
        config(['database.default' => 'mask_test', 'database.connections.mask_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            foreach (['staff_number', 'full_names', 'national_id', 'email_address', 'mobile_number', 'hire_date'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['current_status_id', 'department_id', 'position_id'] as $column) {
                $t->integer($column)->nullable();
            }
        });
        Schema::create('sys_set_departments', function (Blueprint $t) { $t->id(); $t->string('department_name')->nullable(); });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) { $t->id(); $t->string('position_name')->nullable(); });
        DB::table('sys_staff')->insert([
            ['staff_number' => 'PER001', 'full_names' => 'MARY WANJIKU', 'national_id' => '12345678', 'current_status_id' => 1],
            ['staff_number' => 'PER002', 'full_names' => 'NO ID PERSON', 'national_id' => null, 'current_status_id' => 1],
        ]);

        $json = app(HrReportsController::class)->getStaffDirectory(Request::create('/', 'GET'))->getData(true);

        $this->assertTrue($json['success']);
        $this->assertSame(['****5678', '-'], [$json['data'][0]['national_id'], $json['data'][1]['national_id']], 'listed by name: Mary, then the person with no ID');
        $this->assertStringNotContainsString('12345678', json_encode($json));
        $this->assertSame('12345678', Staff::where('staff_number', 'PER001')->value('national_id'), 'the saved number is untouched');
    }

    public function test_the_other_lists_are_masked_in_their_code(): void
    {
        $this->assertStringContainsString('MaskedNumber::orDash($member->national_id)', file_get_contents(resource_path('views/staff/index.blade.php')));
        $reports = file_get_contents(app_path('Http/Controllers/HrReportsController.php'));
        $this->assertStringContainsString("MaskedNumber::orDash(\$r->account_number, '-')", $reports);
        $this->assertStringContainsString("MaskedNumber::orDash(\$r->national_id, '-')", $reports);
        $this->assertStringContainsString('MaskedNumber::tail($employee->national_id)', file_get_contents(app_path('Http/Controllers/PayslipsController.php')));
    }

    public function test_the_profile_the_edit_form_and_the_exports_still_read_the_full_numbers(): void
    {
        $details = file_get_contents(resource_path('views/staff/details.blade.php'));
        $edit = file_get_contents(resource_path('views/staff/edit.blade.php'));

        $this->assertStringContainsString('$staff->national_id', $details);
        $this->assertStringContainsString('$staff->account_number', $details);
        $this->assertStringNotContainsString('MaskedNumber', $details . $edit, 'the profile and the edit form show the real number');
        $this->assertStringNotContainsString('MaskedNumber', file_get_contents(app_path('Services/PayrollPreRunCheck.php')));
        $this->assertStringNotContainsString('MaskedNumber', file_get_contents(app_path('Exports/StaffExport.php')), 'the staff export has the full numbers');
    }
}

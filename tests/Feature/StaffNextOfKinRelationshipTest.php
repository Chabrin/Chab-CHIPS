<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffOnboardingController;
use App\Models\Staff;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HR records how each next of kin is related to the staff member: asked on the public onboarding form,
 * carried to Add Staff, editable on Edit Staff and shown on the profile.
 */
class StaffNextOfKinRelationshipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_make_onboarding_employment_type_nullable.php'))->up();
        Schema::create('sys_set_banks', function (Blueprint $table) { $table->id(); $table->string('bank_name'); });
        Schema::create('sys_staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number')->nullable();
            $table->string('full_names')->nullable();
            $table->string('national_id')->nullable();
            $table->string('email_address')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('kins_names')->nullable();
            $table->string('photo')->nullable();
        });
        (require database_path('migrations/2026_11_03_000002_add_signature_to_sys_staff.php'))->up();
        (require database_path('migrations/2026_11_05_210000_add_kins_relationship_to_sys_staff.php'))->up();
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('employee_id');
            $table->string('file_path');
        });
        Storage::fake('local');
        Storage::fake('public');
    }

    private function actingAsHr(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function invitation(string $status, ?array $submission = null): StaffOnboardingInvitation
    {
        return StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test',
            'token_hash' => hash('sha256', str_repeat($status[0], 64)),
            'invited_by' => 7,
            'status' => $status,
            'submission' => $submission,
            'expires_at' => now()->addDay(),
        ]);
    }

    /** What the public onboarding form posts. */
    private function submit(array $overrides = [])
    {
        DB::table('sys_set_banks')->insertOrIgnore(['id' => 1, 'bank_name' => 'Test Bank']);

        return $this->post(route('staff.onboarding.submit', str_repeat('s', 64)), $overrides + [
            'full_names' => 'Example Staff', 'gender' => 'Female', 'birth_date' => '1995-02-03',
            'national_id' => '12345678', 'marital' => 'Single', 'citizenship' => 'RESIDENT', 'disability' => 'NO',
            'mobile_number' => '0700000000', 'personal_email' => 'me@example.test',
            'bank_name' => 1, 'account_name' => 'Example Staff', 'account_number' => '001', 'pin_number' => 'A001',
            'nhif_number' => 'SHA1', 'nssf_number' => 'NSSF1',
            'documents' => [
                'national_id' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nid"),
                'kra_pin' => UploadedFile::fake()->createWithContent('pin.pdf', "%PDF-1.4\npin"),
            ],
        ]);
    }

    public function test_the_public_form_asks_how_the_next_of_kin_is_related(): void
    {
        $this->invitation('sent');

        $page = $this->get(route('staff.onboarding.complete', str_repeat('s', 64)))->assertOk()
            ->assertSee('Next of kin names')->assertSee('Relationship to you')
            ->assertSee('name="kins_relationship"', false);

        foreach (Staff::KIN_RELATIONSHIPS as $relationship) {
            $page->assertSee('<option >' . $relationship . '</option>', false);
        }
    }

    public function test_the_relationship_is_saved_with_the_onboarding_submission(): void
    {
        $this->invitation('sent');

        $this->submit(['kins_names' => 'Jane Example', 'kins_relationship' => 'Sibling'])->assertOk()->assertSee('Thank you');

        $submission = StaffOnboardingInvitation::sole()->submission;
        $this->assertSame('Jane Example', $submission['kins_names']);
        $this->assertSame('Sibling', $submission['kins_relationship']);
    }

    public function test_a_next_of_kin_name_without_a_relationship_is_sent_back(): void
    {
        $this->invitation('sent');

        $this->submit(['kins_names' => 'Jane Example'])->assertSessionHasErrors(['kins_relationship']);

        $this->assertSame('sent', StaffOnboardingInvitation::sole()->status);
    }

    public function test_a_relationship_that_is_not_on_the_list_is_refused(): void
    {
        $this->invitation('sent');

        $this->submit(['kins_names' => 'Jane Example', 'kins_relationship' => 'Landlord'])->assertSessionHasErrors(['kins_relationship']);
    }

    public function test_next_of_kin_stays_optional_on_the_public_form(): void
    {
        $this->invitation('sent');

        $this->submit()->assertOk()->assertSee('Thank you');

        $this->assertArrayNotHasKey('kins_relationship', StaffOnboardingInvitation::sole()->submission);
    }

    public function test_hr_sees_the_relationship_when_reviewing_the_submission(): void
    {
        $this->actingAsHr();
        $invitation = $this->invitation('submitted', ['full_names' => 'Example Staff', 'kins_names' => 'Jane Example', 'kins_relationship' => 'Parent']);

        // The page itself needs the whole menu; the rows HR reads are built by the controller
        $sections = app(StaffOnboardingController::class)->review($invitation)->getData()['sections'];

        $this->assertSame('Jane Example', $sections['Contact Information']['Next of Kin Names']);
        $this->assertSame('Parent', $sections['Contact Information']['Next of Kin Relationship']);
    }

    public function test_add_staff_asks_for_the_relationship_when_a_name_is_given(): void
    {
        $this->actingAsHr();

        $errors = $this->post(route('staff.store'), ['kins_names' => 'Jane Example'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->json('errors');

        $this->assertArrayHasKey('kins_relationship', $errors);
        $this->assertStringContainsString('next of kin relationship', $errors['kins_relationship'][0]);
    }

    public function test_add_staff_needs_no_relationship_without_a_next_of_kin(): void
    {
        $this->actingAsHr();

        $errors = $this->post(route('staff.store'), [], ['Accept' => 'application/json'])->assertUnprocessable()->json('errors');

        $this->assertArrayNotHasKey('kins_relationship', $errors);
    }

    public function test_add_staff_refuses_a_relationship_that_is_not_on_the_list(): void
    {
        $this->actingAsHr();

        $errors = $this->post(route('staff.store'), ['kins_names' => 'Jane Example', 'kins_relationship' => 'Landlord'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->json('errors');

        $this->assertArrayHasKey('kins_relationship', $errors);
    }

    public function test_add_staff_accepts_each_listed_relationship(): void
    {
        $this->actingAsHr();

        foreach (Staff::KIN_RELATIONSHIPS as $relationship) {
            $errors = $this->post(route('staff.store'), ['kins_names' => 'Jane Example', 'kins_relationship' => $relationship], ['Accept' => 'application/json'])
                ->assertUnprocessable()->json('errors');

            $this->assertArrayNotHasKey('kins_relationship', $errors, $relationship);
        }
    }

    public function test_editing_a_staff_record_does_not_force_a_relationship_on_older_records(): void
    {
        $this->actingAsHr();
        $id = DB::table('sys_staff')->insertGetId(['staff_number' => 'E-001', 'kins_names' => 'Jane Example']);

        $errors = $this->put(route('staff.update', $id), ['kins_names' => 'Jane Example'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->json('errors');

        $this->assertArrayNotHasKey('kins_relationship', $errors);
    }

    public function test_editing_refuses_a_relationship_that_is_not_on_the_list(): void
    {
        $this->actingAsHr();
        $id = DB::table('sys_staff')->insertGetId(['staff_number' => 'E-001']);

        $errors = $this->put(route('staff.update', $id), ['kins_relationship' => 'Landlord'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->json('errors');

        $this->assertArrayHasKey('kins_relationship', $errors);
    }

    public function test_the_relationship_can_be_saved_on_a_staff_record(): void
    {
        $staff = new Staff();
        $staff->fill(['kins_names' => 'Jane Example', 'kins_relationship' => 'Spouse']);

        $this->assertSame('Spouse', $staff->kins_relationship);
    }

    public function test_the_migration_adds_the_column_once_and_removes_it_again(): void
    {
        $migration = require database_path('migrations/2026_11_05_210000_add_kins_relationship_to_sys_staff.php');

        $migration->up(); // already there from setUp: running again must not fail
        $this->assertTrue(Schema::hasColumn('sys_staff', 'kins_relationship'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('sys_staff', 'kins_relationship'));
    }
}

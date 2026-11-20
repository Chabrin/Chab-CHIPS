<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * Mobile numbers start with the country code and company emails are firstname.surname@chabrinagencies.co.ke,
 * on the onboarding form, Add Staff and Edit Staff.
 */
class StaffContactFormatTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const COMPANY_EMAIL_PLACEHOLDER = 'placeholder="firstname.surname@chabrinagencies.co.ke"';

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        (require database_path('migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_make_onboarding_employment_type_nullable.php'))->up();
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        Schema::create('sys_set_banks', function (Blueprint $table) { $table->id(); $table->string('bank_name'); });
        Schema::create('sys_staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number')->nullable();
            $table->string('full_names')->nullable();
            $table->string('national_id')->nullable();
            $table->string('email_address')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('photo')->nullable();
        });
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('employee_id');
            $table->string('file_path');
        });
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actingAsHr(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function staff(array $attributes): int
    {
        return DB::table('sys_staff')->insertGetId($attributes + ['staff_number' => 'E-' . uniqid()]);
    }

    private function invitation(): StaffOnboardingInvitation
    {
        return StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test', 'token_hash' => hash('sha256', str_repeat('s', 64)),
            'invited_by' => 7, 'status' => 'sent', 'expires_at' => now()->addDay(),
        ]);
    }

    /** What the public onboarding form posts. */
    private function submit(array $overrides = [])
    {
        DB::table('sys_set_banks')->insertOrIgnore(['id' => 1, 'bank_name' => 'Test Bank']);

        return $this->post(route('staff.onboarding.submit', str_repeat('s', 64)), $overrides + [
            'full_names' => 'Example Staff', 'gender' => 'Female', 'birth_date' => '1995-02-03',
            'national_id' => '12345678', 'marital' => 'Single', 'citizenship' => 'RESIDENT', 'disability' => 'NO',
            'mobile_number' => '0700 000 000', 'personal_email' => 'me@example.test',
            'bank_name' => 1, 'account_name' => 'Example Staff', 'account_number' => '001', 'pin_number' => 'A001',
            'nhif_number' => 'SHA1', 'nssf_number' => 'NSSF1', 'data_notice_agreed' => '1', 'is_helb_beneficiary' => 'NO',
            'documents' => [
                'national_id' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nid"),
                'kra_pin' => UploadedFile::fake()->createWithContent('pin.pdf', "%PDF-1.4\npin"),
            ],
        ]);
    }

    private function errors($response): array
    {
        return $response->assertUnprocessable()->json('errors');
    }

    // ── the onboarding form ───────────────────────────────────────────────

    public function test_the_onboarding_form_shows_the_number_format(): void
    {
        $this->invitation();

        $this->get(route('staff.onboarding.complete', str_repeat('s', 64)))->assertOk()
            ->assertSee('placeholder="+254 7XX XXX XXX"', false)
            ->assertSee('data-mobile-format', false)
            ->assertSee('Start with the country code', false)
            ->assertSee('assets/js/staff-contact-format.js', false);
    }

    public function test_a_local_number_is_kept_with_its_country_code(): void
    {
        $this->invitation();

        $this->submit(['mobile_number' => '0712 345 678'])->assertOk()->assertSee('Thank you');

        $this->assertSame('+254712345678', StaffOnboardingInvitation::sole()->submission['mobile_number']);
    }

    public function test_a_number_that_already_has_its_country_code_is_kept_as_it_is(): void
    {
        $this->invitation();

        $this->submit(['mobile_number' => '+254 712 345 678'])->assertOk();
        $this->assertSame('+254712345678', StaffOnboardingInvitation::sole()->submission['mobile_number']);
    }

    public function test_a_number_from_another_country_is_kept_with_its_own_code(): void
    {
        $this->invitation();

        $this->submit(['mobile_number' => '+256 772 123456'])->assertOk();
        $this->assertSame('+256772123456', StaffOnboardingInvitation::sole()->submission['mobile_number']);
    }

    public function test_a_number_that_cannot_be_read_is_sent_back_and_nothing_is_kept(): void
    {
        $this->invitation();

        $this->submit(['mobile_number' => '12345'])->assertSessionHasErrors('mobile_number');

        $this->assertSame('sent', StaffOnboardingInvitation::sole()->status);
    }

    public function test_the_number_is_still_needed_on_the_onboarding_form(): void
    {
        $this->invitation();

        $this->submit(['mobile_number' => ''])->assertSessionHasErrors('mobile_number');
    }

    // ── Add Staff ─────────────────────────────────────────────────────

    public function test_add_staff_refuses_a_mobile_number_without_a_country_code_it_cannot_add(): void
    {
        $this->actingAsHr();

        $errors = $this->errors($this->postJson(route('staff.store'), ['mobile_number' => '12345']));

        $this->assertSame('Enter the mobile number with its country code, like +254 7XX XXX XXX.', $errors['mobile_number'][0]);
    }

    public function test_add_staff_accepts_a_local_number_because_it_adds_the_country_code(): void
    {
        $this->actingAsHr();

        $errors = $this->errors($this->postJson(route('staff.store'), ['mobile_number' => '0712 345 678']));

        $this->assertArrayNotHasKey('mobile_number', $errors);
    }

    public function test_add_staff_refuses_an_official_email_that_is_not_in_the_company_format(): void
    {
        $this->actingAsHr();

        foreach (['lucy@chabrinagencies.co.ke', 'lucy.karanja@gmail.com', 'lucy.wambui.karanja@chabrinagencies.co.ke'] as $email) {
            $errors = $this->errors($this->postJson(route('staff.store'), ['email_address' => $email]));

            $this->assertSame('Use the company email format: firstname.surname@chabrinagencies.co.ke.', $errors['email_address'][0], $email);
        }
    }

    public function test_add_staff_accepts_the_company_format_in_any_capitals(): void
    {
        $this->actingAsHr();

        foreach (['lucy.karanja@chabrinagencies.co.ke', 'Lucy.Karanja@ChabrinAgencies.co.ke'] as $email) {
            $errors = $this->errors($this->postJson(route('staff.store'), ['email_address' => $email]));

            $this->assertArrayNotHasKey('email_address', $errors, $email);
        }
    }

    public function test_add_staff_sees_an_email_in_capitals_as_the_one_already_used(): void
    {
        $this->actingAsHr();
        $this->staff(['email_address' => 'lucy.karanja@chabrinagencies.co.ke']);

        $errors = $this->errors($this->postJson(route('staff.store'), ['email_address' => 'LUCY.KARANJA@chabrinagencies.co.ke']));

        $this->assertStringContainsString('already been taken', $errors['email_address'][0]);
    }

    public function test_add_staff_holds_the_login_email_to_the_same_format(): void
    {
        $this->actingAsHr();

        $errors = $this->errors($this->postJson(route('staff.store'), ['employment_type' => 'Permanent', 'login_email' => 'lucy@gmail.com']));

        $this->assertStringContainsString('company email format', $errors['login_email'][0]);
    }

    public function test_add_staff_needs_no_login_email_for_casual_staff(): void
    {
        $this->actingAsHr();

        $errors = $this->errors($this->postJson(route('staff.store'), ['employment_type' => 'Casual']));

        $this->assertArrayNotHasKey('login_email', $errors);
    }

    // ── Edit Staff ───────────────────────────────────────────────────

    public function test_editing_does_not_hold_an_older_record_to_the_new_format(): void
    {
        $this->actingAsHr();
        $id = $this->staff(['mobile_number' => '0722728512', 'email_address' => 'old.person@gmail.com']);

        $errors = $this->errors($this->putJson(route('staff.update', $id), ['mobile_number' => '0722728512', 'email_address' => 'old.person@gmail.com']));

        $this->assertArrayNotHasKey('mobile_number', $errors);
        $this->assertArrayNotHasKey('email_address', $errors);
    }

    public function test_editing_holds_a_changed_mobile_number_and_email_to_the_format(): void
    {
        $this->actingAsHr();
        $id = $this->staff(['mobile_number' => '0722728512', 'email_address' => 'old.person@gmail.com']);

        $errors = $this->errors($this->putJson(route('staff.update', $id), ['mobile_number' => '12345', 'email_address' => 'new.person@gmail.com']));

        $this->assertArrayHasKey('mobile_number', $errors);
        $this->assertArrayHasKey('email_address', $errors);
    }

    public function test_editing_accepts_a_changed_number_and_email_in_the_new_format(): void
    {
        $this->actingAsHr();
        $id = $this->staff(['mobile_number' => '0722728512', 'email_address' => 'old.person@gmail.com']);

        $errors = $this->errors($this->putJson(route('staff.update', $id), ['mobile_number' => '0733 111 222', 'email_address' => 'new.person@chabrinagencies.co.ke']));

        $this->assertArrayNotHasKey('mobile_number', $errors);
        $this->assertArrayNotHasKey('email_address', $errors);
    }

    // ── the pages ───────────────────────────────────────────────────────

    private function addStaffPage(): string
    {
        $this->actingAsHr();

        return view('staff.create', [
            'departments' => collect(), 'banks' => collect(), 'positions' => collect(), 'zones' => collect(),
            'zoneAreas' => collect(), 'roles' => collect(), 'nextStaffNumber' => 'E-001',
            'onboarding' => null, 'prefill' => [], 'onboardingFiles' => [],
            'categories' => \App\Models\EmploymentCategory::active()->ordered()->get(), 'defaultCategory' => 'Permanent',
        ])->render();
    }

    private function editStaffPage(): string
    {
        $this->actingAsHr();
        $staff = new Staff();
        $staff->forceFill(['id' => 5, 'staff_number' => 'E-005', 'full_names' => 'EXAMPLE STAFF']);

        return view('staff.edit', [
            'staff' => $staff, 'departments' => collect(), 'banks' => collect(), 'positions' => collect(),
            'zones' => collect(), 'zoneAreas' => collect(), 'documentSlots' => collect(),
        ])->render();
    }

    public function test_add_staff_shows_the_number_and_email_formats(): void
    {
        $page = $this->addStaffPage();

        $this->assertStringContainsString('placeholder="+254 7XX XXX XXX" data-mobile-format', $page);
        $this->assertSame(3, substr_count($page, self::COMPANY_EMAIL_PLACEHOLDER), 'the two official email boxes and the login email');
        $this->assertStringContainsString('Format: firstname.surname@chabrinagencies.co.ke', $page);
    }

    public function test_add_staff_gives_the_script_the_company_domain_and_loads_it(): void
    {
        config(['staff.email_domain' => 'example.co.ke']);

        $page = $this->addStaffPage();

        $this->assertStringContainsString('data-email-domain="example.co.ke"', $page);
        $this->assertSame(3, substr_count($page, 'placeholder="firstname.surname@example.co.ke"'));
        $this->assertSame(1, preg_match('/<script src="[^"]*assets\/js\/staff-contact-format\.js\?v=\d+"><\/script>/', $page));
        $this->assertStringContainsString('StaffContactFormat.suggestEmail', $page);
    }

    public function test_add_staff_has_no_role_box_and_each_position_carries_its_role_and_there_is_a_date_agreed_box(): void
    {
        $this->actingAsHr();
        $role = new \App\Models\Role();
        $role->display_name = 'Accountant';
        $position = new \App\Models\Position();
        $position->forceFill(['id' => 4, 'position_name' => 'Chief Accountant', 'role_id' => 3]);
        $position->setRelation('role', $role);

        $page = view('staff.create', [
            'departments' => collect(), 'banks' => collect(), 'positions' => collect([$position]), 'zones' => collect(),
            'zoneAreas' => collect(), 'roles' => collect(), 'nextStaffNumber' => 'E-001',
            'onboarding' => null, 'prefill' => [], 'onboardingFiles' => [],
            'categories' => \App\Models\EmploymentCategory::active()->ordered()->get(), 'defaultCategory' => 'Permanent',
        ])->render();

        $this->assertStringNotContainsString('name="role_id"', $page, 'no role to choose: it comes from the position');
        $this->assertStringContainsString('id="role_display"', $page);
        $this->assertMatchesRegularExpression('/<option value="4"[^>]*data-role-id="3"[^>]*data-role-name="Accountant"/s', $page);
        $this->assertStringContainsString('js-position-star', $page, 'the position is marked required for staff who get a login');
        // The data protection notice: its own dark text, and the day it was agreed (today unless they agreed earlier), not in the future
        $this->assertStringContainsString('id="dataNotice" style="background:#f8f9fa;color:#24352c;"', $page);
        $this->assertStringContainsString('name="data_notice_date"', $page);
        $this->assertStringContainsString('value="' . now()->format('Y-m-d') . '" max="' . now()->format('Y-m-d') . '"', $page);
    }

    public function test_edit_staff_shows_the_number_and_email_formats(): void
    {
        $page = $this->editStaffPage();

        $this->assertStringContainsString('placeholder="+254 7XX XXX XXX" data-mobile-format', $page);
        $this->assertStringContainsString(self::COMPANY_EMAIL_PLACEHOLDER, $page);
        $this->assertSame(1, preg_match('/<script src="[^"]*assets\/js\/staff-contact-format\.js\?v=\d+"><\/script>/', $page));
    }
}

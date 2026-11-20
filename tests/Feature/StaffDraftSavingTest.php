<?php

namespace Tests\Feature;

use App\Mail\SetPasswordMail;
use App\Models\EmployeeDocument;
use App\Models\Staff;
use App\Models\StaffDraft;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use App\Services\PasswordSetupLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * Saving a staff member from a draft: the files kept in the draft are filed with the new record (as onboarding files are),
 * the draft and its folder go, and a draft that is not the user's or a save that fails leaves things as they were.
 */
class StaffDraftSavingTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const ME = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        (require database_path('migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_make_onboarding_employment_type_nullable.php'))->up();
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        (require database_path('migrations/2026_11_11_100000_create_staff_drafts_table.php'))->up();
        (require database_path('migrations/2026_11_13_100000_add_onboarding_tracking_to_staff_drafts.php'))->up();

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            foreach (array_merge((new Staff())->getFillable(), ['signature']) as $column) {
                $t->string($column)->nullable();
            }
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            foreach (['name', 'username', 'email', 'password', 'activation', 'params', 'otpKey', 'otep', 'lastResetTime', 'reset_token', 'reset_token_expires_at', 'registerDate', 'lastvisitDate'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['block', 'sendEmail', 'resetCount', 'requireReset', 'staff_id', 'is_field_officer'] as $column) {
                $t->integer($column)->nullable();
            }
        });
        (require database_path('migrations/2026_11_18_100000_add_password_setup_to_josya_users.php'))->up();
        Schema::create('sys_user_staff_links', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('staff_id'); });
        (require database_path('migrations/2026_10_10_000003_create_password_securities_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_add_staff_password_history_and_reminders.php'))->up();
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('display_name')->nullable(); $t->string('description')->nullable(); $t->boolean('is_global')->default(false); $t->timestamps(); });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) {
            $t->id();
            $t->integer('department_id')->nullable();
            $t->string('position_name')->nullable();
            $t->string('slug_name')->nullable();
            $t->integer('current_status_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('role_id')->nullable();
        });
        Schema::create('sys_set_zones', function (Blueprint $t) { $t->id(); $t->string('zone_name')->nullable(); $t->integer('zone_manager_id')->nullable(); $t->integer('zone_supervisor_id')->nullable(); });
        Schema::create('document_types', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('contract_id')->nullable();
            $t->integer('document_type_id');
            $t->string('title')->nullable();
            $t->string('file_path');
            $t->string('file_name')->nullable();
            $t->integer('file_size')->nullable();
            $t->string('mime_type')->nullable();
            $t->integer('uploaded_by')->nullable();
            $t->timestamps();
        });

        DB::table('cb_roles')->insert(['id' => 1, 'name' => 'hr', 'display_name' => 'HR']);
        // Position 4 is the one the form sends; its role is the HR role. The Add Staff form has no role box: the role comes from the position.
        DB::table('sys_set_staff_positions')->insert(['id' => 4, 'position_name' => 'Accountant', 'role_id' => 1]);
        config(['staff_documents.exception_roles' => ['hr']]);
        DB::table('cb_role_user')->insert(['user_id' => self::ME, 'role_id' => 1]);
        foreach (['National ID / Passport', 'KRA PIN Certificate', 'Signed Employment Contract', 'Curriculum Vitae (CV)', 'Academic Certificates & Transcripts', 'HELB Compliance Certificate'] as $i => $name) {
            DB::table('document_types')->insert(['id' => $i + 1, 'name' => $name]);
        }

        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->shouldReceive('isAbleTo')->andReturn(true);
        $user->id = self::ME;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    /** Everything the form needs except the files. */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'full_names' => 'Mary Ann Wanjiku', 'employment_type' => 'Contract',
            'email_address' => 'mary.a.wanjiku@chabrinagencies.co.ke', 'login_email' => 'mary.login@chabrinagencies.co.ke', 'position_id' => 4,
            'national_id' => '12345678', 'gender' => 'Female', 'birth_date' => '1990-01-01', 'bank_name' => 1,
            'account_name' => 'Mary Wanjiku', 'account_number' => '0001', 'pin_number' => 'A000000000E', 'nhif_number' => '1', 'nssf_number' => '1', 'data_notice_agreed' => '1',
            // The HELB certificate is mandatory; these tests are signed in as an HR Manager who waives it with a reason (tested on its own below)
            'is_helb_beneficiary' => 'NO', 'helb_exception' => '1', 'helb_exception_reason' => 'Waived for this test, not applicable.',
            'marital' => 'Single', 'citizenship' => 'RESIDENT', 'disability' => 'NO',
        ];
    }

    private function draftWith(array $files, int $owner = self::ME): StaffDraft
    {
        $draft = StaffDraft::create(['created_by' => $owner, 'title' => 'MARY', 'payload' => ['fields' => ['full_names' => 'Mary'], 'files' => []]]);
        $kept = [];
        foreach ($files as $field => [$name, $text, $extra]) {
            $path = $draft->directory() . '/' . md5($name) . '.' . pathinfo($name, PATHINFO_EXTENSION);
            Storage::disk('local')->put($path, $text);
            $kept[$field] = ['path' => $path, 'name' => $name, 'size' => strlen($text), 'mime' => $extra['mime'] ?? 'application/pdf'] + ($extra['type'] ?? [] ? ['type_name' => $extra['type']] : []);
        }
        $draft->payload = ['fields' => ['full_names' => 'Mary'], 'files' => $kept];
        $draft->save();

        return $draft;
    }

    private function twoDocuments(): array
    {
        return [
            'documents.national_id' => ['id.pdf', "%PDF-1.4\ndraft id", ['type' => 'National ID / Passport']],
            'documents.kra_pin' => ['pin.pdf', "%PDF-1.4\ndraft pin", ['type' => 'KRA PIN Certificate']],
        ];
    }

    private function store(array $data, array $files = [])
    {
        return $this->call('POST', route('staff.store'), $data, [], $files, ['HTTP_ACCEPT' => 'application/json']);
    }

    private function docs(Staff $staff)
    {
        return EmployeeDocument::where('employee_id', $staff->id)->get()->keyBy('title');
    }

    // ── a draft becomes a staff member ────────────────────────────────────

    public function test_saving_from_a_draft_files_its_documents_with_the_new_staff_member(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $response = $this->store($this->form(['draft_id' => $draft->id]));

        $response->assertOk()->assertJsonStructure(['redirect_url']);
        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $docs = $this->docs($staff);
        $this->assertSame(['KRA PIN Certificate', 'National ID / Passport'], $docs->keys()->sort()->values()->all());
        foreach ($docs as $document) {
            $this->assertStringStartsWith("employee_documents/{$staff->id}/", $document->file_path);
            Storage::disk('local')->assertExists($document->file_path);
        }
        $this->assertSame(['id.pdf', 'pin.pdf'], $docs->pluck('file_name')->sort()->values()->all(), 'with the names they had');
        $this->assertSame("%PDF-1.4\ndraft id", Storage::disk('local')->get($docs['National ID / Passport']->file_path));
    }

    public function test_the_draft_and_its_folder_are_removed_once_the_staff_member_is_saved(): void
    {
        $draft = $this->draftWith($this->twoDocuments());
        $folder = $draft->directory();

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk();

        $this->assertSame(0, StaffDraft::open()->count(), 'nothing is left to reopen');
        $this->assertSame(0, StaffDraft::visibleTo(self::ME)->count());
        $this->assertSame([], Storage::disk('local')->files($folder), 'the draft\'s own copies are gone');

        // What stays is an emptied record of the onboarding, with nothing typed or attached in it
        $record = StaffDraft::firstOrFail();
        $this->assertNotNull($record->completed_at);
        $this->assertSame([], $record->fields());
        $this->assertSame([], $record->files());
    }

    public function test_a_photo_in_the_draft_becomes_the_staff_photo_and_a_signature_the_staff_signature(): void
    {
        $draft = $this->draftWith($this->twoDocuments() + [
            'photo' => ['me.png', 'photo bytes', ['mime' => 'image/png']],
            'signature' => ['signature-abc.png', 'signature bytes', ['mime' => 'image/png']],
        ]);

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertStringStartsWith('staff_photos/', $staff->photo);
        $this->assertSame('photo bytes', Storage::disk('public')->get($staff->photo));
        $this->assertStringStartsWith("staff_signatures/{$staff->id}/", $staff->signature);
        $this->assertSame('signature bytes', Storage::disk('local')->get($staff->signature));
    }

    public function test_a_file_chosen_on_the_form_wins_over_the_one_in_the_draft_for_that_box_only(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id]), ['documents' => ['national_id' => UploadedFile::fake()->createWithContent('new-id.pdf', "%PDF-1.4\nbrand new id")]])->assertOk();

        $docs = $this->docs(Staff::where('national_id', '12345678')->firstOrFail());
        $this->assertSame('new-id.pdf', $docs['National ID / Passport']->file_name, 'the new file is the one filed');
        $this->assertSame('pin.pdf', $docs['KRA PIN Certificate']->file_name, 'the other still comes from the draft');
        $this->assertSame(2, $docs->count());
        $this->assertSame(0, StaffDraft::open()->count());
    }

    public function test_saving_without_a_draft_still_needs_the_uploads_and_works_with_them(): void
    {
        $this->store($this->form())->assertUnprocessable()->assertJsonValidationErrors(['documents.national_id', 'documents.kra_pin']);

        $this->store($this->form(), ['documents' => [
            'national_id' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\na"),
            'kra_pin' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\nb"),
        ]])->assertOk();

        $this->assertSame(2, EmployeeDocument::count());
    }

    // ── when it does not go through ───────────────────────────────────────

    public function test_a_save_that_is_refused_leaves_the_draft_and_its_files_where_they_were(): void
    {
        $draft = $this->draftWith($this->twoDocuments());
        DB::table('sys_staff')->insert(['national_id' => '12345678', 'email_address' => 'someone.else@chabrinagencies.co.ke']);

        $this->store($this->form(['draft_id' => $draft->id]))->assertUnprocessable()->assertJsonValidationErrors('national_id');

        $this->assertNotNull(StaffDraft::find($draft->id));
        foreach ($draft->files() as $file) {
            Storage::disk('local')->assertExists($file['path']);
        }
        $this->assertSame(1, Staff::count(), 'no second staff member');
    }

    public function test_a_colleagues_draft_is_used_and_finished_like_any_other_and_stays_credited_to_who_began_it(): void
    {
        $theirs = $this->draftWith($this->twoDocuments(), 99);

        $this->store($this->form(['draft_id' => $theirs->id]))->assertOk();

        $this->assertNotNull(Staff::where('national_id', '12345678')->first());
        $this->assertSame(0, StaffDraft::open()->count());
        $this->assertSame(99, (int) StaffDraft::findOrFail($theirs->id)->created_by);
        $this->assertSame(2, EmployeeDocument::count(), 'with its files');
    }

    public function test_a_draft_id_that_no_longer_exists_is_ignored_and_the_uploads_are_asked_for(): void
    {
        $this->store($this->form(['draft_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors('documents.national_id');
    }

    // ── a draft that began as an onboarding submission ────────────────────

    public function test_files_from_onboarding_and_from_the_draft_are_both_filed_and_both_are_removed(): void
    {
        Storage::disk('local')->put('staff_onboarding/1/id.pdf', "%PDF-1.4\nthe staff member's id");
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test', 'token_hash' => hash('sha256', str_repeat('s', 64)),
            'invited_by' => self::ME, 'status' => 'reviewed', 'expires_at' => now()->addDay(),
            'submission' => ['full_names' => 'Mary', 'onboarding_documents' => [['type_name' => 'National ID / Passport', 'path' => 'staff_onboarding/1/id.pdf', 'name' => 'their-id.pdf', 'size' => 30, 'mime' => 'application/pdf']]],
        ]);
        $draft = $this->draftWith(['documents.kra_pin' => ['pin.pdf', "%PDF-1.4\ndraft pin", ['type' => 'KRA PIN Certificate']]]);

        $this->store($this->form(['draft_id' => $draft->id, 'onboarding_invitation_id' => $invitation->id]))->assertOk();

        $docs = $this->docs(Staff::where('national_id', '12345678')->firstOrFail());
        $this->assertSame('their-id.pdf', $docs['National ID / Passport']->file_name, 'from onboarding');
        $this->assertSame('pin.pdf', $docs['KRA PIN Certificate']->file_name, 'from the draft');
        $this->assertSame('approved', $invitation->fresh()->status);
        $this->assertSame(0, StaffDraft::open()->count());
        $this->assertSame([], Storage::disk('local')->files('staff_onboarding/1'));
    }

    public function test_adding_staff_needs_the_data_protection_notice_agreed_and_records_it_with_the_date_and_who(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 11:00:00'));
        $draft = $this->draftWith($this->twoDocuments());
        $withoutIt = $this->form(['draft_id' => $draft->id]);
        unset($withoutIt['data_notice_agreed']);

        $this->store($withoutIt)->assertStatus(422)->assertJsonValidationErrors('data_notice_agreed');
        $this->assertSame(0, Staff::count(), 'nothing is saved without the agreement');

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertSame('2026-11-18 11:00:00', $staff->data_notice_agreed_at->format('Y-m-d H:i:s'));
        $this->assertSame(self::ME, (int) $staff->data_notice_recorded_by, 'HR recorded it');
    }

    public function test_the_date_agreed_today_keeps_the_time_it_was_recorded(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-19 10:15:00'));
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'data_notice_date' => '2026-11-19']))->assertOk();

        $this->assertSame('2026-11-19 10:15:00', Staff::where('national_id', '12345678')->firstOrFail()->data_notice_agreed_at->format('Y-m-d H:i:s'));
    }

    public function test_an_earlier_date_agreed_is_the_day_they_agreed_for_example_on_a_paper_copy(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-19 10:15:00'));
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'data_notice_date' => '2026-11-05']))->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertSame('2026-11-05', $staff->data_notice_agreed_at->format('Y-m-d'));
        $this->assertSame(self::ME, (int) $staff->data_notice_recorded_by, 'HR still recorded it');
    }

    public function test_a_date_agreed_in_the_future_is_refused_and_leaving_it_out_means_today(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-19 10:15:00'));
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'data_notice_date' => '2026-11-25']))->assertStatus(422)->assertJsonValidationErrors('data_notice_date');
        $this->assertSame(0, Staff::count());

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk();
        $this->assertSame('2026-11-19', Staff::where('national_id', '12345678')->firstOrFail()->data_notice_agreed_at->format('Y-m-d'));
    }

    public function test_an_agreement_the_staff_member_gave_online_is_carried_onto_their_record_not_asked_again(): void
    {
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test', 'token_hash' => hash('sha256', str_repeat('t', 64)),
            'invited_by' => self::ME, 'status' => 'reviewed', 'expires_at' => now()->addDay(),
            'submission' => ['full_names' => 'Mary', 'onboarding_documents' => [], 'data_notice_agreed_at' => '2026-11-12 09:30:00'],
        ]);
        $draft = $this->draftWith($this->twoDocuments());
        $form = $this->form(['draft_id' => $draft->id, 'onboarding_invitation_id' => $invitation->id]);
        unset($form['data_notice_agreed']);

        $this->store($form)->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertSame('2026-11-12 09:30:00', $staff->data_notice_agreed_at->format('Y-m-d H:i:s'), 'the date they agreed, not the date HR saved');
        $this->assertNull($staff->data_notice_recorded_by, 'nobody recorded it for them');
    }

    // ---- the HELB compliance certificate (mandatory; only the HR Manager can waive it, with a reason) ----

    /** The form as a colleague who is not the HR Manager would send it: no exception, so the certificate is needed. */
    private function formNeedingTheCertificate(array $overrides = []): array
    {
        $form = $this->form($overrides);
        unset($form['helb_exception'], $form['helb_exception_reason']);

        return $form;
    }

    private function helbFile(): array
    {
        return ['documents' => ['helb' => UploadedFile::fake()->createWithContent('helb.pdf', "%PDF-1.4\nhelb certificate")]];
    }

    public function test_the_helb_certificate_is_required_unless_an_exception_is_recorded(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $response = $this->store($this->formNeedingTheCertificate(['draft_id' => $draft->id]))->assertStatus(422)->assertJsonValidationErrors('documents.helb');

        $this->assertStringContainsString('HELB compliance certificate is required', $response->json('errors')['documents.helb'][0]);
        $this->assertSame(0, Staff::count());
    }

    public function test_uploading_the_helb_certificate_is_enough_and_it_is_filed_with_the_staff_member(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->formNeedingTheCertificate(['draft_id' => $draft->id]), $this->helbFile())->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertArrayHasKey('HELB Compliance Certificate', $this->docs($staff));
        $this->assertNull($staff->helb_certificate_exception_at, 'nothing was waived');
    }

    public function test_only_the_hr_manager_can_record_an_exception(): void
    {
        DB::table('cb_role_user')->where('user_id', self::ME)->delete();
        DB::table('cb_roles')->insert(['id' => 2, 'name' => 'office_assistant', 'display_name' => 'Office Assistant']);
        DB::table('cb_role_user')->insert(['user_id' => self::ME, 'role_id' => 2]);
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id]))->assertStatus(422)->assertJsonValidationErrors('helb_exception');

        $this->assertSame(0, Staff::count());
    }

    public function test_an_exception_needs_a_written_reason(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'helb_exception_reason' => '']))->assertStatus(422)->assertJsonValidationErrors('helb_exception_reason');
        $this->store($this->form(['draft_id' => $draft->id, 'helb_exception_reason' => 'short']))->assertStatus(422)->assertJsonValidationErrors('helb_exception_reason');
    }

    public function test_an_exception_records_who_allowed_it_when_and_why(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 12:00:00'));
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'helb_exception_reason' => 'Employee never studied in Kenya; no HELB account exists.']))->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertSame('Employee never studied in Kenya; no HELB account exists.', $staff->helb_certificate_exception_reason);
        $this->assertSame(self::ME, (int) $staff->helb_certificate_exception_by);
        $this->assertSame('2026-11-18 12:00:00', $staff->helb_certificate_exception_at->format('Y-m-d H:i:s'));
    }

    public function test_a_certificate_that_is_uploaded_wins_over_an_exception_that_was_also_ticked(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id]), $this->helbFile())->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertNull($staff->helb_certificate_exception_at, 'no exception is recorded when the certificate is there');
        $this->assertArrayHasKey('HELB Compliance Certificate', $this->docs($staff));
    }

    public function test_the_beneficiary_question_must_be_answered_and_a_yes_prompts_for_the_deduction(): void
    {
        $draft = $this->draftWith($this->twoDocuments());
        $unanswered = $this->form(['draft_id' => $draft->id]);
        unset($unanswered['is_helb_beneficiary']);

        $this->store($unanswered)->assertStatus(422)->assertJsonValidationErrors('is_helb_beneficiary');

        $this->store($this->form(['draft_id' => $draft->id, 'is_helb_beneficiary' => 'YES']))->assertOk();
        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertTrue($staff->is_helb_beneficiary);
        $this->assertSame(['name' => 'MARY ANN WANJIKU'], session('helb_prompt'));
    }

    public function test_a_no_answer_gives_no_prompt(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'is_helb_beneficiary' => 'NO']))->assertOk();

        $this->assertFalse(Staff::where('national_id', '12345678')->firstOrFail()->is_helb_beneficiary);
        $this->assertNull(session('helb_prompt'));
    }

    public function test_a_certificate_the_staff_member_sent_through_onboarding_is_enough(): void
    {
        Storage::disk('local')->put('staff_onboarding/1/helb.pdf', "%PDF-1.4\ntheir helb certificate");
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test', 'token_hash' => hash('sha256', str_repeat('h', 64)),
            'invited_by' => self::ME, 'status' => 'reviewed', 'expires_at' => now()->addDay(),
            'submission' => ['full_names' => 'Mary', 'data_notice_agreed_at' => '2026-11-12 09:30:00', 'onboarding_documents' => [
                ['type_name' => 'HELB Compliance Certificate', 'path' => 'staff_onboarding/1/helb.pdf', 'name' => 'their-helb.pdf', 'size' => 30, 'mime' => 'application/pdf'],
            ]],
        ]);
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->formNeedingTheCertificate(['draft_id' => $draft->id, 'onboarding_invitation_id' => $invitation->id]))->assertOk();

        $docs = $this->docs(Staff::where('national_id', '12345678')->firstOrFail());
        $this->assertSame('their-helb.pdf', $docs['HELB Compliance Certificate']->file_name);
    }

    public function test_adding_someone_who_worked_here_before_points_to_rehire_with_their_staff_number(): void
    {
        DB::table('sys_staff')->insert(['full_names' => 'MARY OLD RECORD', 'staff_number' => 'PER009', 'national_id' => '12345678', 'current_status_id' => 3]);
        $draft = $this->draftWith($this->twoDocuments());

        $response = $this->store($this->form(['draft_id' => $draft->id]))->assertStatus(422)->assertJsonValidationErrors('national_id');

        $message = $response->json('errors')['national_id'][0];
        $this->assertStringContainsString('MARY OLD RECORD', $message);
        $this->assertStringContainsString('PER009', $message);
        $this->assertStringContainsString('Rehire', $message);
        $this->assertSame(1, Staff::count(), 'nobody was added');
    }

    public function test_adding_someone_still_working_here_gets_the_ordinary_duplicate_message(): void
    {
        DB::table('sys_staff')->insert(['full_names' => 'MARY CURRENT', 'staff_number' => 'PER010', 'national_id' => '12345678', 'current_status_id' => 1]);
        $draft = $this->draftWith($this->twoDocuments());

        $response = $this->store($this->form(['draft_id' => $draft->id]))->assertStatus(422)->assertJsonValidationErrors('national_id');

        $this->assertStringNotContainsString('Rehire', $response->json('errors')['national_id'][0]);
    }

    // ---- citizenship and residency ----

    public function test_a_form_that_does_not_send_the_country_means_kenya_and_resident(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertSame(['Kenya', 'RESIDENT', 'RESIDENT'], [$staff->nationality, $staff->residency_status, $staff->citizenship]);
    }

    public function test_a_staff_member_from_another_country_needs_residency_passport_and_a_permit_but_not_a_national_id(): void
    {
        $draft = $this->draftWith($this->twoDocuments());
        $form = $this->form(['draft_id' => $draft->id, 'nationality' => 'Uganda', 'residency_status' => '', 'national_id' => null]);

        $errors = $this->store($form)->assertStatus(422)->json('errors');

        $this->assertEqualsCanonicalizing(['residency_status', 'passport', 'work_permit_number', 'work_permit_expiry'], array_keys($errors));
        $this->assertSame(0, Staff::count());
    }

    public function test_a_staff_member_from_another_country_is_saved_with_their_permit_and_no_national_id(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form([
            'draft_id' => $draft->id, 'nationality' => 'Uganda', 'residency_status' => 'NON_RESIDENT', 'national_id' => null,
            'passport' => 'B1234567', 'work_permit_number' => 'WP-2026-001', 'work_permit_expiry' => '2027-06-30',
        ]))->assertOk();

        $staff = Staff::where('passport', 'B1234567')->firstOrFail();
        $this->assertSame('Uganda', $staff->nationality);
        $this->assertSame('NON_RESIDENT', $staff->residency_status);
        $this->assertSame('WP-2026-001', $staff->work_permit_number);
        $this->assertSame('2027-06-30', $staff->work_permit_expiry->format('Y-m-d'));
        $this->assertNull($staff->national_id);
        $this->assertSame('NO', $staff->citizenship, 'the old column keeps its Resident / NO answer');
    }

    public function test_a_country_that_is_not_on_the_list_is_refused_when_adding_staff(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'nationality' => 'Atlantis']))->assertStatus(422)->assertJsonValidationErrors('nationality');
    }

    private function roleIdsOf(string $loginEmail): array
    {
        $userId = User::where('email', $loginEmail)->value('id');

        return DB::table('cb_role_user')->where('user_id', $userId)->pluck('role_id')->map(fn ($v) => (int) $v)->all();
    }

    public function test_the_role_of_the_position_decides_the_logins_role_and_a_role_the_form_sent_is_ignored(): void
    {
        DB::table('cb_roles')->insert(['id' => 2, 'name' => 'accountant', 'display_name' => 'Accountant']);
        DB::table('sys_set_staff_positions')->insert(['id' => 5, 'position_name' => 'Cashier', 'role_id' => 2]);
        $draft = $this->draftWith($this->twoDocuments());

        // An old or tampered form sends role 1 (HR) for a position whose role is 2
        $this->store($this->form(['draft_id' => $draft->id, 'position_id' => 5, 'role_id' => 1]))->assertOk();

        $this->assertSame([2], $this->roleIdsOf('mary.login@chabrinagencies.co.ke'), 'the position decides');
    }

    public function test_a_staff_member_who_gets_a_login_must_have_a_position_because_the_role_comes_from_it(): void
    {
        $draft = $this->draftWith($this->twoDocuments());
        $form = $this->form(['draft_id' => $draft->id]);
        unset($form['position_id']);

        $this->store($form)->assertStatus(422)->assertJsonValidationErrors('position_id');
        $this->assertSame(0, Staff::count());

        $this->store($this->form(['draft_id' => $draft->id, 'position_id' => 999]))->assertStatus(422)->assertJsonValidationErrors('position_id');
        $this->assertSame(0, Staff::count(), 'a position that does not exist is no position');
    }

    public function test_a_position_not_linked_to_a_role_yet_is_matched_to_the_role_with_its_name(): void
    {
        DB::table('cb_roles')->insert(['id' => 3, 'name' => 'accountant', 'display_name' => 'Accountant']);
        DB::table('sys_set_staff_positions')->insert(['id' => 6, 'position_name' => 'Accountant', 'slug_name' => 'accountant', 'role_id' => null]);
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'position_id' => 6]))->assertOk();

        $this->assertSame([3], $this->roleIdsOf('mary.login@chabrinagencies.co.ke'));
        $this->assertSame(3, (int) DB::table('sys_set_staff_positions')->where('id', 6)->value('role_id'), 'the position is linked for next time');
        $this->assertSame(2, DB::table('cb_roles')->count(), 'no duplicate role was made');
    }

    public function test_a_position_with_no_role_and_no_matching_role_gets_a_new_role_with_no_access(): void
    {
        DB::table('sys_set_staff_positions')->insert(['id' => 7, 'position_name' => 'Records Clerk', 'slug_name' => 'records_clerk', 'role_id' => null]);
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'position_id' => 7]))->assertOk();

        $roleId = (int) DB::table('sys_set_staff_positions')->where('id', 7)->value('role_id');
        $this->assertGreaterThan(1, $roleId);
        $this->assertSame([$roleId], $this->roleIdsOf('mary.login@chabrinagencies.co.ke'));
        $this->assertSame('Records Clerk', DB::table('cb_roles')->where('id', $roleId)->value('display_name'));
    }

    public function test_a_file_in_the_draft_replaces_the_one_from_onboarding_for_the_same_box(): void
    {
        Storage::disk('local')->put('staff_onboarding/1/id.pdf', "%PDF-1.4\nfrom onboarding");
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test', 'token_hash' => hash('sha256', str_repeat('s', 64)),
            'invited_by' => self::ME, 'status' => 'reviewed', 'expires_at' => now()->addDay(),
            'submission' => ['full_names' => 'Mary', 'onboarding_documents' => [['type_name' => 'National ID / Passport', 'path' => 'staff_onboarding/1/id.pdf', 'name' => 'their-id.pdf', 'size' => 30, 'mime' => 'application/pdf']]],
        ]);
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'onboarding_invitation_id' => $invitation->id]))->assertOk();

        $docs = $this->docs(Staff::where('national_id', '12345678')->firstOrFail());
        $this->assertSame('id.pdf', $docs['National ID / Passport']->file_name, 'what HR attached to the draft wins');
        $this->assertSame(2, $docs->count());
    }

    // ── what stays once the staff member is saved ─────────────────────────

    private function saveDraft(array $fields = []): StaffDraft
    {
        $id = $this->call('POST', route('staff.drafts.save'), $fields + ['full_names' => 'Draft Person', 'employment_type' => 'Permanent'], [], [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk()->json('draft_id');

        return StaffDraft::findOrFail($id);
    }

    /** The uploads the form requires, for a draft that was saved with none. */
    private function uploads(): array
    {
        return ['documents' => [
            'national_id' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\na"),
            'kra_pin' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\nb"),
        ]];
    }

    private function existingStaff(string $number, string $type = 'Permanent'): void
    {
        DB::table('sys_staff')->insert(['staff_number' => $number, 'employment_type' => $type, 'national_id' => 'existing-' . $number]);
    }

    public function test_what_stays_is_a_record_of_who_was_saved_and_how_far_it_got(): void
    {
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test', 'token_hash' => hash('sha256', str_repeat('s', 64)),
            'invited_by' => self::ME, 'status' => 'reviewed', 'expires_at' => now()->addDay(), 'submission' => ['full_names' => 'Mary'],
        ]);
        $draft = $this->draftWith($this->twoDocuments());
        $draft->forceFill(['stages' => ['personal' => 'complete', 'employment' => 'pending']])->save();
        $this->travel(3)->days();

        $this->store($this->form(['draft_id' => $draft->id, 'onboarding_invitation_id' => $invitation->id]))->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $record = StaffDraft::findOrFail($draft->id);
        $this->assertSame($staff->id, (int) $record->staff_id);
        $this->assertSame($staff->staff_number, $record->staff_number);
        $this->assertSame('Contract', $record->employment_type);
        $this->assertSame($invitation->id, (int) $record->onboarding_invitation_id);
        $this->assertSame('MARY', $record->title, 'the name it already had');
        $this->assertSame(array_fill_keys(array_keys(StaffDraft::STAGES), 'complete'), $record->stageStatuses(), 'everything on Add Staff is done');
        $this->assertTrue($record->completed_at->isToday() || $record->completed_at->isFuture());
        $this->assertTrue($record->created_at->lt($record->completed_at), 'so the days it took can be told');
    }

    public function test_a_form_saved_without_any_draft_leaves_a_finished_record_too(): void
    {
        $this->store($this->form(), ['documents' => [
            'national_id' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\na"),
            'kra_pin' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\nb"),
        ]])->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $record = StaffDraft::firstOrFail();
        $this->assertSame($staff->id, (int) $record->staff_id);
        $this->assertSame(self::ME, (int) $record->created_by);
        $this->assertSame('MARY ANN WANJIKU', $record->title);
        $this->assertNotNull($record->completed_at);
        $this->assertSame([], $record->fields());
    }

    public function test_a_finished_record_is_not_a_draft_to_reopen_list_or_purge(): void
    {
        $draft = $this->draftWith($this->twoDocuments());
        $this->store($this->form(['draft_id' => $draft->id]))->assertOk();
        $this->travel(90)->days();

        $this->assertNull(StaffDraft::visibleTo(self::ME)->find($draft->id));
        $this->getJson(route('staff.drafts.index'))->assertOk()->assertJsonCount(0, 'drafts');
        $this->artisan('hr:purge-staff-drafts', ['--days' => 30, '--apply' => true])->assertSuccessful();
        $this->assertNotNull(StaffDraft::find($draft->id), 'the record of a saved staff member is not purged');
    }

    public function test_the_stages_of_a_casual_staff_member_leave_system_user_out(): void
    {
        $this->assertSame('skipped', StaffDraft::finishedStages(true)['user']);
        $this->assertSame('complete', StaffDraft::finishedStages(true)['personal']);
        $this->assertSame('complete', StaffDraft::finishedStages(false)['user']);
    }

    public function test_a_record_that_cannot_be_kept_does_not_make_the_save_fail(): void
    {
        // The database refuses to finish the record, but the staff member is already saved by then
        DB::unprepared("CREATE TRIGGER refuse_finish BEFORE UPDATE OF completed_at ON staff_drafts BEGIN SELECT RAISE(ABORT, 'cannot keep the record'); END;");
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk()->assertJsonStructure(['redirect_url']);

        $this->assertNotNull(Staff::where('national_id', '12345678')->first(), 'the staff member was saved');
        $this->assertNull($draft->fresh()->completed_at, 'and the record was left as it was');
    }

    // ── on to the contract ────────────────────────────────────────────────

    /**
     * The contract form's menu entry. The plain layout helper makes the menu tables without the address column; with an entry
     * the role must have been given it, without one the form is open to everyone.
     */
    private function contractMenu(?bool $roleHasIt): void
    {
        if (!Schema::hasColumn('cb_sub_modules', 'sub_module_url')) {
            Schema::table('cb_sub_modules', function (Blueprint $t) { $t->string('sub_module_url')->nullable(); });
        }
        if ($roleHasIt === null) {
            return;
        }
        DB::table('cb_sub_modules')->insert(['id' => 5, 'sub_module_url' => '/hr/contracts/create']);
        DB::table('cb_role_user')->insert(['user_id' => self::ME, 'role_id' => 1]);
        if ($roleHasIt) {
            DB::table('cb_sub_module_permissions')->insert(['role_id' => 1, 'sub_module_id' => 5]);
        }
    }

    public function test_saving_goes_straight_on_to_the_contract_form_with_the_staff_member_and_position_chosen(): void
    {
        $this->contractMenu(null);
        $response = $this->store($this->form(['position_id' => 4]), ['documents' => [
            'national_id' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\na"),
            'kra_pin' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\nb"),
        ]])->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertSame(route('contract.create', ['staff_id' => $staff->id, 'position_id' => 4]), $response->json('redirect_url'));
        $this->assertStringContainsString('staff_id=' . $staff->id, $response->json('redirect_url'));
        $this->assertStringContainsString('position_id=4', $response->json('redirect_url'));
    }

    public function test_the_new_login_starts_with_no_usable_password_and_the_person_is_emailed_a_link(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'password' => 'Admin123456!']))->assertOk();

        $user = User::where('email', 'mary.login@chabrinagencies.co.ke')->firstOrFail();
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('Admin123456!', $user->password), 'A password typed on the form is never used');
        $this->assertSame(PasswordSetupLink::WAITING, app(PasswordSetupLink::class)->stateOf($user));
        Mail::assertSent(SetPasswordMail::class, 1);
        Mail::assertSent(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo('mary.login@chabrinagencies.co.ke'));
        $this->assertSame(['sent' => true, 'email' => 'mary.login@chabrinagencies.co.ke'], session('account_setup'));
        $this->assertNull(session('generated_password'), 'No password is shown to whoever added the staff member');
    }

    public function test_a_failed_email_does_not_undo_the_save_and_says_so(): void
    {
        Mail::swap(new class {
            public function to(...$arguments)
            {
                throw new \RuntimeException('smtp is down');
            }
        });
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk()->assertJsonStructure(['redirect_url']);

        $this->assertNotNull(Staff::where('national_id', '12345678')->first(), 'The staff member is saved');
        $this->assertNotNull(User::where('email', 'mary.login@chabrinagencies.co.ke')->first(), 'and so is the login');
        $this->assertSame(['sent' => false, 'email' => 'mary.login@chabrinagencies.co.ke'], session('account_setup'));
    }

    public function test_staff_without_a_login_are_sent_nothing(): void
    {
        DB::table('employment_categories')->where('code', 'Casual')->update(['gets_login' => 0]);
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id, 'employment_type' => 'Casual', 'login_email' => null, 'position_id' => null]))->assertOk();

        Mail::assertNothingSent();
        $this->assertNull(session('account_setup'));
    }

    public function test_the_message_says_there_is_no_contract_yet(): void
    {
        $draft = $this->draftWith($this->twoDocuments());

        $this->store($this->form(['draft_id' => $draft->id]))->assertOk();

        $this->assertStringContainsString('no contract yet', session('success'));
    }

    public function test_a_user_who_cannot_open_the_contract_form_is_sent_to_the_profile_instead(): void
    {
        $this->contractMenu(false);

        $draft = $this->draftWith($this->twoDocuments());
        $response = $this->store($this->form(['draft_id' => $draft->id]))->assertOk();

        $this->assertStringStartsWith(substr(route('staff.show', 'x'), 0, -1), $response->json('redirect_url'), 'the profile');
        $this->assertStringNotContainsString('/hr/contracts/create', $response->json('redirect_url'));
        $this->assertStringContainsString('still needs to be added', session('success'));
    }

    public function test_a_user_whose_role_was_given_the_contract_form_is_sent_to_it(): void
    {
        $this->contractMenu(true);

        $draft = $this->draftWith($this->twoDocuments());
        $response = $this->store($this->form(['draft_id' => $draft->id]))->assertOk();

        $this->assertStringContainsString('/hr/contracts/create', $response->json('redirect_url'));
    }

    // ── the staff number a draft holds ────────────────────────────────────

    public function test_the_staff_member_gets_the_number_held_for_the_draft(): void
    {
        $this->existingStaff('120');
        $draft = $this->saveDraft();
        $this->assertSame('121', $draft->staff_number);

        $this->store($this->form(['draft_id' => $draft->id, 'employment_type' => 'Permanent']), $this->uploads())->assertOk();

        $this->assertSame('121', Staff::where('national_id', '12345678')->firstOrFail()->staff_number);
    }

    public function test_the_other_drafts_keep_their_numbers_when_one_becomes_a_staff_member(): void
    {
        $this->existingStaff('120');
        [$a, $b, $c] = [$this->saveDraft(), $this->saveDraft(), $this->saveDraft()];
        $this->assertSame(['121', '122', '123'], [$a->staff_number, $b->staff_number, $c->staff_number]);

        $this->store($this->form(['draft_id' => $b->id, 'employment_type' => 'Permanent']), $this->uploads())->assertOk();

        $this->assertSame('122', Staff::where('national_id', '12345678')->firstOrFail()->staff_number);
        $this->assertSame('121', $a->fresh()->staff_number);
        $this->assertSame('123', $c->fresh()->staff_number, 'nothing shifts when a draft is used');
        $this->assertSame('124', Staff::nextStaffNumber('Permanent'), 'and the next new one carries on after the highest');
    }

    public function test_a_number_someone_took_meanwhile_is_not_used_and_the_next_free_one_is(): void
    {
        $this->existingStaff('120');
        $draft = $this->saveDraft();
        $this->existingStaff('121');

        $this->store($this->form(['draft_id' => $draft->id, 'employment_type' => 'Permanent']), $this->uploads())->assertOk();

        $this->assertSame('122', Staff::where('national_id', '12345678')->firstOrFail()->staff_number, 'never a second 121');
        $this->assertSame(1, Staff::where('staff_number', '121')->count(), 'only one staff member holds 121');
    }

    public function test_a_draft_saved_in_another_category_releases_its_number_and_the_others_close_up(): void
    {
        $this->existingStaff('120');
        [$a, $b] = [$this->saveDraft(), $this->saveDraft()];
        $this->assertSame(['121', '122'], [$a->staff_number, $b->staff_number]);

        // HR changed the category to Contract just before saving
        $this->store($this->form(['draft_id' => $a->id, 'employment_type' => 'Contract']), $this->uploads())->assertOk();

        $this->assertSame('CT-001', Staff::where('national_id', '12345678')->firstOrFail()->staff_number);
        $this->assertSame('121', $b->fresh()->staff_number, 'it takes the number the other draft gave back');
    }

    public function test_a_form_saved_without_a_draft_does_not_take_a_number_a_draft_holds(): void
    {
        $this->existingStaff('120');
        $draft = $this->saveDraft();

        $this->store($this->form(['employment_type' => 'Permanent']), $this->uploads())->assertOk();

        $this->assertSame('122',Staff::where('national_id', '12345678')->firstOrFail()->staff_number);
        $this->assertSame('121', $draft->fresh()->staff_number);
    }
}

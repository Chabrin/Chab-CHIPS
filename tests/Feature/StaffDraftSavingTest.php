<?php

namespace Tests\Feature;

use App\Models\EmployeeDocument;
use App\Models\Staff;
use App\Models\StaffDraft;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        Schema::create('sys_user_staff_links', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('staff_id'); });
        (require database_path('migrations/2026_10_10_000003_create_password_securities_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_add_staff_password_history_and_reminders.php'))->up();
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('display_name')->nullable(); });
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
        foreach (['National ID / Passport', 'KRA PIN Certificate', 'Signed Employment Contract', 'Curriculum Vitae (CV)', 'Academic Certificates & Transcripts'] as $i => $name) {
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
            'email_address' => 'mary.a.wanjiku@chabrinagencies.co.ke', 'login_email' => 'mary.login@chabrinagencies.co.ke', 'role_id' => 1,
            'national_id' => '12345678', 'gender' => 'Female', 'birth_date' => '1990-01-01', 'bank_name' => 1,
            'account_name' => 'Mary Wanjiku', 'account_number' => '0001', 'pin_number' => 'A000000000E', 'nhif_number' => '1', 'nssf_number' => '1',
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
        Schema::create('sys_set_staff_positions', function (Blueprint $t) { $t->id(); $t->integer('department_id')->nullable(); $t->string('position_name')->nullable(); $t->string('slug_name')->nullable(); });
        DB::table('sys_set_staff_positions')->insert(['id' => 4, 'position_name' => 'Accountant']);

        $response = $this->store($this->form(['position_id' => 4]), ['documents' => [
            'national_id' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\na"),
            'kra_pin' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\nb"),
        ]])->assertOk();

        $staff = Staff::where('national_id', '12345678')->firstOrFail();
        $this->assertSame(route('contract.create', ['staff_id' => $staff->id, 'position_id' => 4]), $response->json('redirect_url'));
        $this->assertStringContainsString('staff_id=' . $staff->id, $response->json('redirect_url'));
        $this->assertStringContainsString('position_id=4', $response->json('redirect_url'));
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

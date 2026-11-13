<?php

namespace Tests\Feature;

use App\Models\StaffDraft;
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
 * Save as Draft on Add Staff: a part-finished record, with its files, kept on the server so it can be reopened from any
 * computer. Private to the person who started it, encrypted at rest, and gone when the staff member is saved.
 */
class StaffDraftsTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const ME = 7;
    private const SOMEONE_ELSE = 8;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout(true);
        Storage::fake('local');
        Storage::fake('public');

        (require database_path('migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_make_onboarding_employment_type_nullable.php'))->up();
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        (require database_path('migrations/2026_11_11_100000_create_staff_drafts_table.php'))->up();
        (require database_path('migrations/2026_11_13_100000_add_onboarding_tracking_to_staff_drafts.php'))->up();
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('employment_type')->nullable();
            $t->string('national_id')->nullable();
            $t->string('email_address')->nullable();
            $t->string('photo')->nullable();
        });
        Schema::create('employee_documents', function (Blueprint $t) { $t->id(); $t->integer('employee_id'); $t->string('file_path'); });
        Schema::create('sys_set_banks', function (Blueprint $t) { $t->id(); $t->string('bank_name'); });
        Schema::create('sys_set_departments', function (Blueprint $t) { $t->id(); $t->string('department_name'); });
        Schema::create('sys_set_staff_positions', function (Blueprint $t) { $t->id(); $t->string('position_name'); $t->boolean('is_active')->default(true); });
        Schema::create('sys_set_zones', function (Blueprint $t) { $t->id(); $t->string('zone_name'); });
        Schema::create('sys_set_zone_areas', function (Blueprint $t) { $t->id(); $t->string('area_name'); });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('display_name')->nullable(); });
        DB::table('sys_set_zones')->insert([['id' => 1, 'zone_name' => 'Zone One'], ['id' => 2, 'zone_name' => 'Zone Two']]);
        DB::table('cb_roles')->insert(['id' => 1, 'name' => 'hr', 'display_name' => 'HR']);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actingAsUser(int $id = self::ME, bool $allowed = true): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn($allowed);
        $user->shouldReceive('isAbleTo')->andReturn($allowed);
        $user->id = $id;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function pdf(string $name = 'id.pdf', string $text = 'national id'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n" . $text);
    }

    private function save(array $data = [], array $files = [])
    {
        return $this->call('POST', route('staff.drafts.save'), $data, [], $files, ['HTTP_ACCEPT' => 'application/json']);
    }

    private function draft(int $owner = self::ME, array $fields = ['full_names' => 'Mary Wanjiku'], array $files = []): StaffDraft
    {
        $draft = StaffDraft::create(['created_by' => $owner, 'title' => 'MARY WANJIKU', 'payload' => ['fields' => $fields, 'files' => $files]]);
        foreach ($files as $file) {
            Storage::disk('local')->put($file['path'], 'content of ' . $file['name']);
        }

        return $draft;
    }

    private function savedFile(StaffDraft $draft, string $field, string $name, string $text = 'saved'): array
    {
        $path = $draft->directory() . '/' . md5($name) . '.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n" . $text);

        return [$field => ['path' => $path, 'name' => $name, 'size' => 20, 'mime' => 'application/pdf'] + (str_starts_with($field, 'documents.') ? ['type_name' => 'National ID / Passport'] : [])];
    }

    // ── saving ────────────────────────────────────────────────────────────

    public function test_what_is_typed_is_kept_as_a_new_draft(): void
    {
        $this->actingAsUser();

        $response = $this->save(['full_names' => 'Mary Wanjiku', 'national_id' => '12345678', 'gender' => 'Female', 'last_tab' => 'banking'])->assertOk();

        $draft = StaffDraft::firstOrFail();
        $response->assertJsonPath('success', true)->assertJsonPath('draft_id', $draft->id);
        $this->assertSame(self::ME, (int) $draft->created_by);
        $this->assertSame('MARY WANJIKU', $draft->title);
        $this->assertSame('banking', $draft->last_tab);
        $this->assertSame(['full_names' => 'Mary Wanjiku', 'national_id' => '12345678', 'gender' => 'Female'], $draft->fields());
    }

    public function test_the_answer_says_when_it_was_saved(): void
    {
        $this->actingAsUser();

        $response = $this->save(['full_names' => 'Mary'])->assertOk()->json();

        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $response['saved_at_label']);
        $this->assertNotEmpty($response['saved_at']);
        $this->assertStringContainsString('any computer', $response['message']);
    }

    public function test_the_draft_is_encrypted_at_rest(): void
    {
        $this->actingAsUser();

        $this->save(['full_names' => 'Mary Wanjiku', 'national_id' => '12345678', 'pin_number' => 'A123456789Z', 'account_number' => '0011223344'])->assertOk();

        $stored = DB::table('staff_drafts')->value('payload');
        foreach (['Mary', '12345678', 'A123456789Z', '0011223344'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored, $secret);
        }
    }

    public function test_saving_again_with_the_draft_id_updates_it_instead_of_making_another(): void
    {
        $this->actingAsUser();
        $id = $this->save(['full_names' => 'Mary', 'national_id' => '111'])->json('draft_id');

        $this->save(['draft_id' => $id, 'full_names' => 'Mary Wanjiku', 'bank_name' => '2'])->assertOk()->assertJsonPath('draft_id', $id);

        $this->assertSame(1, StaffDraft::count());
        $this->assertSame(['full_names' => 'Mary Wanjiku', 'bank_name' => '2'], StaffDraft::find($id)->fields(), 'the form as it is now, not a mix');
        $this->assertSame('MARY WANJIKU', StaffDraft::find($id)->title);
    }

    public function test_passwords_and_form_plumbing_are_never_kept(): void
    {
        $this->actingAsUser();

        $this->save(['full_names' => 'Mary', 'password' => 'S3cret!pass', 'password_confirmation' => 'S3cret!pass', '_token' => 'abc', 'draft_id' => ''])->assertOk();

        $fields = StaffDraft::firstOrFail()->fields();
        $this->assertSame(['full_names' => 'Mary'], $fields);
        $this->assertStringNotContainsString('S3cret', json_encode(StaffDraft::firstOrFail()->payload));
    }

    public function test_lists_of_zones_are_kept_but_not_anything_odd(): void
    {
        $this->actingAsUser();

        $this->save([
            'full_names' => 'Mary', 'managed_zone_ids' => ['1', '2'], 'supervised_zone_ids' => ['2'],
            'nested' => ['a' => ['b' => 'c']], 'too_long' => str_repeat('x', 6000), 'empty' => '', 'blank_list' => [''],
            'bad name!' => 'x', 'drop[table]' => 'x',
        ])->assertOk();

        $this->assertSame(['full_names' => 'Mary', 'managed_zone_ids' => ['1', '2'], 'supervised_zone_ids' => ['2']], StaffDraft::firstOrFail()->fields());
    }

    public function test_a_form_with_only_what_it_filled_in_itself_is_not_worth_saving(): void
    {
        $this->actingAsUser();

        $this->save(['employment_type' => 'Permanent', 'staff_number' => '281'])
            ->assertUnprocessable()->assertJsonPath('message', 'There is nothing to save yet. Fill in something first.');

        $this->assertSame(0, StaffDraft::count());
    }

    public function test_a_form_with_only_a_file_is_worth_saving(): void
    {
        $this->actingAsUser();

        $this->save(['employment_type' => 'Permanent'], ['documents' => ['national_id' => $this->pdf()]])->assertOk();

        $this->assertSame(1, StaffDraft::count());
        $this->assertNull(StaffDraft::firstOrFail()->title);
    }

    // ── files ─────────────────────────────────────────────────────────────

    public function test_files_are_kept_on_the_private_disk_and_never_the_public_one(): void
    {
        $this->actingAsUser();

        $id = $this->save(['full_names' => 'Mary'], [
            'photo' => UploadedFile::fake()->image('me.png', 200, 200),
            'documents' => ['national_id' => $this->pdf('my-id.pdf'), 'kra_pin' => $this->pdf('pin.pdf', 'kra')],
        ])->assertOk()->json('draft_id');

        $files = StaffDraft::find($id)->files();
        $this->assertSame(['photo', 'documents.national_id', 'documents.kra_pin'], array_keys($files));
        foreach ($files as $file) {
            $this->assertStringStartsWith("staff_drafts/{$id}/", $file['path']);
            Storage::disk('local')->assertExists($file['path']);
            Storage::disk('public')->assertMissing($file['path']);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_each_file_keeps_its_name_size_type_and_the_document_it_stands_for(): void
    {
        $this->actingAsUser();

        $id = $this->save(['full_names' => 'Mary'], ['documents' => ['national_id' => $this->pdf('my-id.pdf'), 'cv' => UploadedFile::fake()->create('cv.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]])->json('draft_id');

        $files = StaffDraft::find($id)->files();
        $this->assertSame('my-id.pdf', $files['documents.national_id']['name']);
        $this->assertSame('National ID / Passport', $files['documents.national_id']['type_name'], 'so it can be filed with the staff member the way onboarding files are');
        $this->assertSame('Curriculum Vitae (CV)', $files['documents.cv']['type_name']);
        $this->assertGreaterThan(0, $files['documents.national_id']['size']);
        $this->assertSame('application/pdf', $files['documents.national_id']['mime']);
    }

    public function test_saving_again_without_files_keeps_the_ones_saved_before(): void
    {
        $this->actingAsUser();
        $id = $this->save(['full_names' => 'Mary'], ['documents' => ['national_id' => $this->pdf('my-id.pdf')]])->json('draft_id');
        $path = StaffDraft::find($id)->files()['documents.national_id']['path'];

        $this->save(['draft_id' => $id, 'full_names' => 'Mary Wanjiku'])->assertOk();

        $this->assertSame($path, StaffDraft::find($id)->files()['documents.national_id']['path']);
        Storage::disk('local')->assertExists($path);
    }

    public function test_choosing_a_new_file_replaces_the_old_one_and_deletes_it(): void
    {
        $this->actingAsUser();
        $id = $this->save(['full_names' => 'Mary'], ['documents' => ['national_id' => $this->pdf('first.pdf', 'one')]])->json('draft_id');
        $old = StaffDraft::find($id)->files()['documents.national_id']['path'];

        $this->save(['draft_id' => $id, 'full_names' => 'Mary'], ['documents' => ['national_id' => $this->pdf('second.pdf', 'two')]])->assertOk();

        $files = StaffDraft::find($id)->files();
        $this->assertSame('second.pdf', $files['documents.national_id']['name']);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($files['documents.national_id']['path']);
        $this->assertCount(1, Storage::disk('local')->files("staff_drafts/{$id}"));
    }

    public function test_a_drawn_signature_is_kept_as_a_file(): void
    {
        $this->actingAsUser();
        // A real PNG: one transparent pixel
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $id = $this->save(['full_names' => 'Mary', 'signature_data' => 'data:image/png;base64,' . base64_encode($pixel)])->assertOk()->json('draft_id');

        $signature = StaffDraft::find($id)->files()['signature'];
        $this->assertStringStartsWith("staff_drafts/{$id}/signature-", $signature['path']);
        $this->assertSame($pixel, Storage::disk('local')->get($signature['path']));
        $this->assertSame('image/png', $signature['mime']);
        $this->assertArrayNotHasKey('signature_data', StaffDraft::find($id)->fields(), 'the picture is a file, not text in the draft');
    }

    public function test_a_signature_that_cannot_be_read_saves_nothing_at_all(): void
    {
        $this->actingAsUser();

        $this->save(['full_names' => 'Mary', 'signature_data' => 'data:image/png;base64,bm90IGEgcG5n'])->assertUnprocessable()->assertJsonValidationErrors('signature_data');

        $this->assertSame(0, StaffDraft::count(), 'no half-saved draft is left behind');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_new_draft_that_fails_part_way_leaves_no_files_behind_either(): void
    {
        $this->actingAsUser();

        // The document is stored first, then the signature turns out to be unreadable
        $this->save(['full_names' => 'Mary', 'signature_data' => 'data:image/png;base64,bm90IGEgcG5n'], ['documents' => ['national_id' => $this->pdf()]])
            ->assertUnprocessable();

        $this->assertSame(0, StaffDraft::count());
        $this->assertSame([], Storage::disk('local')->allFiles(), 'the file stored before the failure was removed');
    }

    public function test_a_file_of_the_wrong_kind_or_size_is_refused_and_nothing_is_kept(): void
    {
        $this->actingAsUser();

        $this->save(['full_names' => 'Mary'], ['documents' => ['national_id' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])
            ->assertUnprocessable()->assertJsonValidationErrors('documents.national_id');
        $this->save(['full_names' => 'Mary'], ['photo' => UploadedFile::fake()->image('big.png', 100, 100)->size(3000)])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->save(['full_names' => 'Mary'], ['photo' => UploadedFile::fake()->create('photo.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->save(['full_names' => 'Mary'], ['documents' => ['contract' => UploadedFile::fake()->create('contract.docx', 10)]])
            ->assertUnprocessable()->assertJsonValidationErrors('documents.contract');

        $this->assertSame(0, StaffDraft::count());
    }

    // ── whose draft it is ─────────────────────────────────────────────────

    public function test_nobody_without_the_create_staff_permission_can_save_list_or_discard(): void
    {
        $draft = $this->draft();
        $this->actingAsUser(self::ME, false);

        $this->save(['full_names' => 'Mary'])->assertForbidden();
        $this->getJson(route('staff.drafts.index'))->assertForbidden();
        $this->deleteJson(route('staff.drafts.destroy', $draft->id))->assertForbidden();

        $this->assertSame(1, StaffDraft::count());
    }

    public function test_a_colleague_can_carry_on_someone_elses_draft_and_it_stays_theirs_to_show_who_began_it(): void
    {
        $theirs = $this->draft(self::SOMEONE_ELSE, ['full_names' => 'Their Draft']);
        $this->actingAsUser();

        $this->save(['draft_id' => $theirs->id, 'full_names' => 'Carried On'])->assertOk()->assertJsonPath('draft_id', $theirs->id);

        $this->assertSame(['full_names' => 'Carried On'], $theirs->fresh()->fields());
        $this->assertSame(self::SOMEONE_ELSE, (int) $theirs->fresh()->created_by, 'who began it is kept');
        $this->assertSame(self::ME, (int) $theirs->fresh()->updated_by, 'and who saved it last');
        $this->assertSame(1, StaffDraft::count());
    }

    public function test_saving_to_a_draft_that_is_gone_says_so(): void
    {
        $this->actingAsUser();

        $this->save(['draft_id' => 999, 'full_names' => 'Mary'])->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_the_list_is_every_open_draft_newest_first_whoever_began_it(): void
    {
        $old = $this->draft(self::ME, ['full_names' => 'Old One']);
        $old->forceFill(['title' => 'OLD ONE', 'updated_at' => now()->subDays(2)])->save();
        $theirs = $this->draft(self::SOMEONE_ELSE, ['full_names' => 'A Colleagues']);
        $theirs->forceFill(['title' => 'A COLLEAGUES', 'updated_at' => now()->subDay()])->save();
        $new = $this->draft(self::ME, ['full_names' => 'New One']);
        $new->forceFill(['title' => 'NEW ONE'])->save();
        StaffDraft::create(['created_by' => self::ME, 'title' => 'FINISHED', 'payload' => ['fields' => [], 'files' => []], 'completed_at' => now()]);
        $this->actingAsUser();

        $drafts = $this->getJson(route('staff.drafts.index'))->assertOk()->json('drafts');

        $this->assertSame(['NEW ONE', 'A COLLEAGUES', 'OLD ONE'], array_column($drafts, 'title'), 'a finished record is not a draft');
        $this->assertSame(route('staff.create', ['draft' => $new->id]), $drafts[0]['resume_url']);
        $this->assertSame($new->id, $drafts[0]['id']);
    }

    public function test_the_list_shows_how_many_files_each_draft_holds_and_names_an_untitled_one(): void
    {
        $draft = $this->draft(self::ME, ['gender' => 'Female']);
        $draft->forceFill(['title' => null])->save();
        $holder = $this->draft(self::ME, ['full_names' => 'Mary']);
        $files = $this->savedFile($holder, 'documents.national_id', 'id.pdf') + $this->savedFile($holder, 'documents.kra_pin', 'pin.pdf');
        $holder->payload = ['fields' => ['full_names' => 'Mary'], 'files' => $files];
        $holder->save();
        $this->actingAsUser();

        $byId = collect($this->getJson(route('staff.drafts.index'))->json('drafts'))->keyBy('id');

        $this->assertSame('Untitled draft', $byId[$draft->id]['title']);
        $this->assertSame(0, $byId[$draft->id]['files']);
        $this->assertSame(2, $byId[$holder->id]['files']);
        $this->assertMatchesRegularExpression('/^\d{2} \w{3} \d{4}, \d{2}:\d{2}$/', $byId[$draft->id]['updated_label']);
    }

    // ── seeing a saved file ───────────────────────────────────────────────

    private function draftWithFile(int $owner = self::ME): array
    {
        $draft = $this->draft($owner);
        $files = $this->savedFile($draft, 'documents.national_id', 'my-id.pdf', 'the id');
        $draft->payload = ['fields' => ['full_names' => 'Mary'], 'files' => $files];
        $draft->save();

        return [$draft, hash('sha256', $files['documents.national_id']['path'])];
    }

    public function test_the_owner_can_open_a_saved_file_which_is_served_privately(): void
    {
        [$draft, $hash] = $this->draftWithFile();
        $this->actingAsUser();

        $response = $this->get(route('staff.drafts.file', [$draft->id, $hash]))->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_a_saved_file_is_not_found_by_a_wrong_address(): void
    {
        [$draft] = $this->draftWithFile();
        $this->actingAsUser();

        $this->get(route('staff.drafts.file', [$draft->id, str_repeat('a', 64)]))->assertNotFound();
    }

    public function test_a_colleague_who_can_add_staff_can_open_a_saved_file_but_nobody_else_can(): void
    {
        [$draft, $hash] = $this->draftWithFile();

        $this->actingAsUser(self::SOMEONE_ELSE);
        $this->get(route('staff.drafts.file', [$draft->id, $hash]))->assertOk();

        $this->actingAsUser(self::SOMEONE_ELSE, false);
        $this->get(route('staff.drafts.file', [$draft->id, $hash]))->assertForbidden();
    }

    public function test_a_file_address_must_be_a_hash(): void
    {
        [$draft] = $this->draftWithFile();
        $this->actingAsUser();

        $this->get('/hr/employees/drafts/' . $draft->id . '/files/../../secret')->assertNotFound();
        $this->get('/hr/employees/drafts/' . $draft->id . '/files/not-a-hash')->assertNotFound();
    }

    // ── discarding ────────────────────────────────────────────────────────

    public function test_discarding_removes_the_draft_and_every_file_saved_with_it(): void
    {
        $this->actingAsUser();
        $id = $this->save(['full_names' => 'Mary'], ['photo' => UploadedFile::fake()->image('me.png'), 'documents' => ['national_id' => $this->pdf()]])->json('draft_id');
        $this->assertNotEmpty(Storage::disk('local')->files("staff_drafts/{$id}"));

        $this->deleteJson(route('staff.drafts.destroy', $id))->assertOk()->assertJsonPath('message', 'The draft was discarded.');

        $this->assertSame(0, StaffDraft::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_colleague_can_discard_someone_elses_draft(): void
    {
        $theirs = $this->draft(self::SOMEONE_ELSE);
        $this->actingAsUser();

        $this->deleteJson(route('staff.drafts.destroy', $theirs->id))->assertOk();

        $this->assertSame(0, StaffDraft::count());
    }

    public function test_someone_who_cannot_add_staff_cannot_discard_a_draft(): void
    {
        $theirs = $this->draft(self::SOMEONE_ELSE);
        $this->actingAsUser(self::ME, false);

        $this->deleteJson(route('staff.drafts.destroy', $theirs->id))->assertForbidden();

        $this->assertSame(1, StaffDraft::count());
    }

    public function test_a_finished_record_cannot_be_discarded_as_a_draft(): void
    {
        $finished = StaffDraft::create(['created_by' => self::ME, 'payload' => ['fields' => [], 'files' => []], 'completed_at' => now()]);
        $this->actingAsUser();

        $this->deleteJson(route('staff.drafts.destroy', $finished->id))->assertNotFound();

        $this->assertSame(1, StaffDraft::count());
    }

    public function test_discarding_from_the_onboarding_page_goes_back_to_it(): void
    {
        $draft = $this->draft(self::ME);
        $this->actingAsUser();

        $this->delete(route('staff.drafts.destroy', $draft->id))->assertRedirect(route('staff.onboarding.index'))
            ->assertSessionHas('success', 'The draft was discarded.');

        $this->assertSame(0, StaffDraft::count());
    }

    // ── reopening a draft ─────────────────────────────────────────────────

    private function openDraft(StaffDraft $draft)
    {
        return $this->get(route('staff.create', ['draft' => $draft->id]));
    }

    public function test_reopening_a_draft_brings_back_what_was_typed(): void
    {
        $draft = $this->draft(self::ME, [
            'full_names' => 'Mary Ann Wanjiku', 'national_id' => '12345678', 'gender' => 'Female', 'employment_type' => 'Contract',
            'email_address' => 'mary.a.wanjiku@chabrinagencies.co.ke', 'login_email' => 'mary.login@chabrinagencies.co.ke',
            'managed_zone_ids' => ['2'], 'kins_names' => 'John Wanjiku',
        ]);
        $this->actingAsUser();

        $page = $this->openDraft($draft)->assertOk()->getContent();

        $this->assertStringContainsString('value="Mary Ann Wanjiku"', $page);
        $this->assertStringContainsString('value="12345678"', $page);
        $this->assertStringContainsString('value="John Wanjiku"', $page);
        $this->assertStringContainsString('<option value="Contract"', $page);
        $this->assertMatchesRegularExpression('/<option value="Contract"[^>]*selected/', $page, 'the category is the one chosen');
        $this->assertSame(2, substr_count($page, 'value="mary.a.wanjiku@chabrinagencies.co.ke"'), 'both official email boxes');
        $this->assertStringContainsString('value="mary.login@chabrinagencies.co.ke"', $page, 'the login email too');
        $this->assertMatchesRegularExpression('/<option value="2"\s+selected>\s*Zone Two/', $page, 'and the zones picked');
        $this->assertDoesNotMatchRegularExpression('/<option value="1"\s+selected>\s*Zone One/', $page);
    }

    public function test_the_page_knows_which_draft_it_is_and_where_to_save_it(): void
    {
        $draft = $this->draft();
        $this->actingAsUser();

        $page = $this->openDraft($draft)->getContent();

        $this->assertStringContainsString('<input type="hidden" name="draft_id" id="draft_id" value="' . $draft->id . '">', $page);
        $this->assertStringContainsString('data-draft-save-url="' . route('staff.drafts.save') . '"', $page);
        $this->assertStringContainsString('data-drafts-url="' . route('staff.drafts.index') . '"', $page);
        $this->assertStringContainsString('Continuing a saved draft', $page);
        $this->assertStringContainsString('id="discardThisDraft"', $page);
    }

    public function test_a_new_form_has_an_empty_draft_id_and_no_banner(): void
    {
        $this->actingAsUser();

        $page = $this->get(route('staff.create'))->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="draft_id" id="draft_id" value="">', $page);
        $this->assertStringNotContainsString('Continuing a saved draft', $page);
        $this->assertStringContainsString('id="openDrafts"', $page);
        $this->assertStringContainsString('id="draftsModal"', $page);
    }

    public function test_the_draft_buttons_are_only_for_those_who_can_create_staff(): void
    {
        $this->actingAsUser(self::ME, false);

        $page = $this->get(route('staff.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="openDrafts"', $page);
        $this->assertStringNotContainsString('id="draftsModal"', $page);
        $this->assertStringNotContainsString('js-save-draft', $page);
    }

    public function test_every_tab_has_save_as_draft_and_a_place_to_say_when_it_was_saved(): void
    {
        $this->actingAsUser();

        $page = $this->get(route('staff.create'))->getContent();

        $this->assertSame(6, substr_count($page, '<button type="button" class="btn btn-outline-secondary me-1 js-save-draft">'), 'one on each of the six tabs');
        $this->assertSame(6, substr_count($page, 'class="js-draft-note"'));
        $this->assertStringContainsString('Save as Draft', $page);
    }

    public function test_the_form_reports_all_its_problems_instead_of_the_browser_stopping_at_the_first(): void
    {
        $this->actingAsUser();

        $this->assertMatchesRegularExpression('/<form[^>]*id="staffCreateForm"[^>]* novalidate/', $this->get(route('staff.create'))->getContent());
    }

    public function test_files_saved_with_a_draft_come_back_with_a_preview_and_stand_in_for_the_required_upload(): void
    {
        $draft = $this->draft();
        $files = $this->savedFile($draft, 'documents.national_id', 'my-id.pdf') + $this->savedFile($draft, 'photo', 'me.png');
        $draft->payload = ['fields' => ['full_names' => 'Mary'], 'files' => $files];
        $draft->save();
        $this->actingAsUser();

        $page = $this->openDraft($draft)->getContent();

        $this->assertStringContainsString('Saved with your draft: my-id.pdf', $page);
        $this->assertStringContainsString('Photo saved with your draft. Upload a new one only to replace it.', $page);
        $this->assertStringContainsString(route('staff.drafts.file', [$draft->id, hash('sha256', $files['documents.national_id']['path'])]), $page);
        $this->assertDoesNotMatchRegularExpression('/name="documents\[national_id\]"[^>]* required/', $page, 'a saved file is not asked for again');
        $this->assertMatchesRegularExpression('/name="documents\[kra_pin\]"[^>]* required/', $page, 'one that was not saved still is');
        $this->assertStringNotContainsString('Received from onboarding', $page);
    }

    public function test_a_saved_signature_is_shown_as_saved_with_the_draft(): void
    {
        $draft = $this->draft();
        $files = $this->savedFile($draft, 'signature', 'signature-abc.png');
        $draft->payload = ['fields' => ['full_names' => 'Mary'], 'files' => $files];
        $draft->save();
        $this->actingAsUser();

        $page = $this->openDraft($draft)->getContent();

        $this->assertStringContainsString('Saved with your draft. Draw or upload only to replace it.', $page);
    }

    public function test_someone_elses_draft_opens_for_a_colleague_so_the_onboarding_is_not_stuck(): void
    {
        $theirs = $this->draft(self::SOMEONE_ELSE);
        $this->actingAsUser();

        $this->openDraft($theirs)->assertOk();
    }

    public function test_a_finished_record_does_not_open_as_a_draft(): void
    {
        $finished = StaffDraft::create(['created_by' => self::ME, 'payload' => ['fields' => [], 'files' => []], 'completed_at' => now()]);
        $this->actingAsUser();

        $this->openDraft($finished)->assertRedirect(route('staff.create'))->assertSessionHas('error');
    }

    public function test_a_draft_that_was_discarded_does_not_open(): void
    {
        $this->actingAsUser();

        $this->get(route('staff.create', ['draft' => 999]))->assertRedirect(route('staff.create'))
            ->assertSessionHas('error', 'That draft is no longer there. It was discarded, or it has become a staff member.');
    }

    public function test_the_draft_remembers_the_tab_it_was_left_on(): void
    {
        $draft = $this->draft();
        $draft->forceFill(['last_tab' => 'banking'])->save();
        $this->actingAsUser();

        $this->assertStringContainsString('data-last-tab="banking"', $this->openDraft($draft)->getContent());
    }

    // ── stages: the tabs of Add Staff, and how far each one has got ───────

    private function stagesSent(array $stages): string
    {
        return json_encode($stages);
    }

    public function test_the_state_of_each_stage_is_kept_with_the_draft_and_not_as_a_typed_field(): void
    {
        $this->actingAsUser();

        $response = $this->save(['full_names' => 'Mary', 'stages' => $this->stagesSent(['personal' => 'complete', 'employment' => 'problem', 'user' => 'skipped'])])->assertOk();

        $draft = StaffDraft::firstOrFail();
        $this->assertSame(['personal' => 'complete', 'employment' => 'problem', 'user' => 'skipped'], $draft->stages);
        $this->assertSame(['full_names' => 'Mary'], $draft->fields(), 'it is not part of what was typed');
        $response->assertJsonPath('stages.personal', 'complete')->assertJsonPath('stages.contact', 'pending');
    }

    public function test_every_stage_has_a_state_even_when_only_some_were_sent(): void
    {
        $draft = $this->draft();
        $draft->forceFill(['stages' => ['personal' => 'complete']])->save();

        $this->assertSame(
            ['personal' => 'complete', 'employment' => 'pending', 'contact' => 'pending', 'banking' => 'pending', 'documents' => 'pending', 'user' => 'pending'],
            $draft->fresh()->stageStatuses()
        );
    }

    public function test_unknown_stages_and_unknown_states_are_dropped(): void
    {
        $this->actingAsUser();

        $this->save(['full_names' => 'Mary', 'stages' => $this->stagesSent(['personal' => 'complete', 'banking' => 'finished', 'hacker' => 'complete', 'documents' => ['x']])])->assertOk();

        $this->assertSame(['personal' => 'complete'], StaffDraft::firstOrFail()->stages);
    }

    public function test_saving_without_stages_keeps_the_ones_saved_before(): void
    {
        $this->actingAsUser();
        $id = $this->save(['full_names' => 'Mary', 'stages' => $this->stagesSent(['personal' => 'complete', 'employment' => 'complete'])])->json('draft_id');

        $this->save(['draft_id' => $id, 'full_names' => 'Mary Wanjiku'])->assertOk();
        $this->assertSame(['personal' => 'complete', 'employment' => 'complete'], StaffDraft::find($id)->stages);

        $this->save(['draft_id' => $id, 'full_names' => 'Mary Wanjiku', 'stages' => 'not json'])->assertOk();
        $this->assertSame(['personal' => 'complete', 'employment' => 'complete'], StaffDraft::find($id)->stages);
    }

    public function test_the_newest_stages_replace_the_old_ones(): void
    {
        $this->actingAsUser();
        $id = $this->save(['full_names' => 'Mary', 'stages' => $this->stagesSent(['personal' => 'problem'])])->json('draft_id');

        $this->save(['draft_id' => $id, 'full_names' => 'Mary', 'stages' => $this->stagesSent(['personal' => 'complete', 'employment' => 'pending'])])->assertOk();

        $this->assertSame(['personal' => 'complete', 'employment' => 'pending'], StaffDraft::find($id)->stages);
    }

    public function test_an_overlong_stages_value_is_refused(): void
    {
        $this->actingAsUser();

        $this->save(['full_names' => 'Mary', 'stages' => str_repeat('x', 700)])->assertUnprocessable();
        $this->assertSame(0, StaffDraft::count());
    }

    public function test_the_list_of_drafts_says_where_each_one_has_got_to(): void
    {
        $draft = $this->draft();
        $draft->forceFill(['last_tab' => 'banking', 'stages' => ['personal' => 'complete', 'employment' => 'complete', 'contact' => 'complete']])->save();
        $this->actingAsUser();

        $row = $this->getJson(route('staff.drafts.index'))->assertOk()->json('drafts.0');

        $this->assertSame('banking', $row['last_tab']);
        $this->assertSame('complete', $row['stages']['contact']);
        $this->assertSame('pending', $row['stages']['banking']);
    }

    public function test_reopening_a_draft_puts_its_stages_on_the_form(): void
    {
        $draft = $this->draft();
        $draft->forceFill(['stages' => ['personal' => 'complete', 'employment' => 'problem']])->save();
        $this->actingAsUser();

        $page = $this->openDraft($draft)->getContent();

        $this->assertStringContainsString('data-stages="{&quot;personal&quot;:&quot;complete&quot;,&quot;employment&quot;:&quot;problem&quot;,', $page);
    }

    public function test_a_new_form_starts_with_no_stages_recorded(): void
    {
        $this->actingAsUser();

        $this->assertStringContainsString('data-stages=""', $this->get(route('staff.create'))->getContent());
    }

    public function test_every_tab_has_a_stage_mark_and_the_page_has_a_summary_to_fill(): void
    {
        $this->actingAsUser();

        $page = $this->get(route('staff.create'))->getContent();

        $this->assertSame(6, substr_count($page, 'js-stage-mark stage-pending'), 'one on each tab');
        $this->assertStringContainsString('id="staffStageSummary"', $page);
        $this->assertStringContainsString('id="staffStageBar"', $page);
    }

    public function test_the_next_button_reads_save_and_next_for_someone_who_can_keep_drafts(): void
    {
        $this->actingAsUser();

        $page = $this->get(route('staff.create'))->getContent();

        $this->assertStringContainsString('Save &amp; Next', $page);
        $this->assertStringNotContainsString('Next Tab', $page);
    }

    public function test_the_next_button_reads_next_tab_for_someone_who_cannot(): void
    {
        $this->actingAsUser(self::ME, false);

        $page = $this->get(route('staff.create'));

        // Without create_staff the page is refused or has no draft saving; either way Save & Next is not offered
        if ($page->getStatusCode() === 200) {
            $this->assertStringNotContainsString('Save &amp; Next', $page->getContent());
        } else {
            $this->assertContains($page->getStatusCode(), [302, 403]);
        }
    }

    // ── a draft that started from an onboarding submission ────────────────

    private function reviewedInvitation(): StaffOnboardingInvitation
    {
        Storage::disk('local')->put('staff_onboarding/1/id.pdf', "%PDF-1.4\nonboarding id");

        return StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test', 'token_hash' => hash('sha256', str_repeat('s', 64)),
            'invited_by' => self::ME, 'status' => 'reviewed', 'expires_at' => now()->addDay(),
            'submission' => [
                'full_names' => 'Example Staff', 'national_id' => '12345678', 'gender' => 'Female',
                'onboarding_documents' => [['type_name' => 'National ID / Passport', 'path' => 'staff_onboarding/1/id.pdf', 'name' => 'their-id.pdf', 'size' => 1, 'mime' => 'application/pdf']],
            ],
        ]);
    }

    public function test_a_draft_started_from_onboarding_goes_back_to_it_and_what_hr_typed_wins(): void
    {
        $invitation = $this->reviewedInvitation();
        $draft = $this->draft(self::ME, ['onboarding_invitation_id' => (string) $invitation->id, 'full_names' => 'Example Staff Corrected', 'bank_name' => '2']);
        $this->actingAsUser();

        $page = $this->openDraft($draft)->assertOk()->getContent();

        $this->assertStringContainsString('Completing onboarding for new.staff@example.test', $page);
        $this->assertStringContainsString('<input type="hidden" name="onboarding_invitation_id" value="' . $invitation->id . '">', $page);
        $this->assertStringContainsString('value="Example Staff Corrected"', $page, 'what HR changed replaces what the staff member entered');
        $this->assertStringContainsString('value="12345678"', $page, 'what they entered and HR left alone stays');
        $this->assertStringContainsString('Received from onboarding: their-id.pdf', $page, 'their file still comes from onboarding');
    }

    public function test_a_draft_whose_onboarding_is_no_longer_waiting_still_opens(): void
    {
        $invitation = $this->reviewedInvitation();
        $invitation->update(['status' => 'approved']);
        $draft = $this->draft(self::ME, ['onboarding_invitation_id' => (string) $invitation->id, 'full_names' => 'Example Staff']);
        $this->actingAsUser();

        $page = $this->openDraft($draft)->assertOk()->getContent();

        $this->assertStringContainsString('value="Example Staff"', $page);
        $this->assertStringNotContainsString('Completing onboarding for', $page);
    }

    public function test_a_file_saved_in_the_draft_replaces_the_one_from_onboarding(): void
    {
        $invitation = $this->reviewedInvitation();
        $draft = $this->draft(self::ME, ['onboarding_invitation_id' => (string) $invitation->id]);
        $files = $this->savedFile($draft, 'documents.national_id', 'my-better-scan.pdf');
        $draft->payload = ['fields' => ['onboarding_invitation_id' => (string) $invitation->id], 'files' => $files];
        $draft->save();
        $this->actingAsUser();

        $page = $this->openDraft($draft)->getContent();

        $this->assertStringContainsString('Saved with your draft: my-better-scan.pdf', $page);
        $this->assertStringNotContainsString('their-id.pdf', $page);
    }

    // ── the final save ────────────────────────────────────────────────────

    private function store(array $data)
    {
        return $this->post(route('staff.store'), $data, ['Accept' => 'application/json']);
    }

    public function test_files_saved_in_a_draft_stand_in_for_the_required_uploads_on_save(): void
    {
        $draft = $this->draft();
        $files = $this->savedFile($draft, 'documents.national_id', 'my-id.pdf') + $this->savedFile($draft, 'documents.kra_pin', 'pin.pdf', 'kra');
        $draft->payload = ['fields' => ['full_names' => 'Mary'], 'files' => $files];
        $draft->save();
        $this->actingAsUser();

        $errors = $this->store(['draft_id' => $draft->id])->assertUnprocessable()->json('errors');

        $this->assertArrayHasKey('full_names', $errors, 'other required fields are still asked for');
        $this->assertArrayNotHasKey('documents.national_id', $errors);
        $this->assertArrayNotHasKey('documents.kra_pin', $errors);
    }

    public function test_without_the_draft_the_same_uploads_are_required(): void
    {
        $this->actingAsUser();

        $errors = $this->store([])->assertUnprocessable()->json('errors');

        $this->assertArrayHasKey('documents.national_id', $errors);
        $this->assertArrayHasKey('documents.kra_pin', $errors);
    }

    public function test_a_colleagues_draft_supplies_its_files_like_any_other(): void
    {
        $theirs = $this->draft(self::SOMEONE_ELSE);
        $files = $this->savedFile($theirs, 'documents.national_id', 'their-id.pdf');
        $theirs->payload = ['fields' => [], 'files' => $files];
        $theirs->save();
        $this->actingAsUser();

        $errors = $this->store(['draft_id' => $theirs->id])->assertUnprocessable()->json('errors');

        $this->assertArrayNotHasKey('documents.national_id', $errors, 'the file in the draft stands in for the upload');
        $this->assertArrayHasKey('documents.kra_pin', $errors, 'the one it does not have is still asked for');
    }

    public function test_a_finished_record_supplies_no_files(): void
    {
        $finished = StaffDraft::create(['created_by' => self::ME, 'payload' => ['fields' => [], 'files' => []], 'completed_at' => now()]);
        $this->actingAsUser();

        $errors = $this->store(['draft_id' => $finished->id])->assertUnprocessable()->json('errors');

        $this->assertArrayHasKey('documents.national_id', $errors);
    }

    public function test_a_file_chosen_now_wins_over_the_one_in_the_draft(): void
    {
        $draft = $this->draft();
        $files = $this->savedFile($draft, 'documents.national_id', 'old.pdf');
        $draft->payload = ['fields' => [], 'files' => $files];
        $draft->save();
        $this->actingAsUser();

        $errors = $this->call('POST', route('staff.store'), ['draft_id' => $draft->id], [], ['documents' => ['national_id' => $this->pdf('new.pdf', 'brand new')]], ['HTTP_ACCEPT' => 'application/json'])
            ->assertUnprocessable()->json('errors');

        $this->assertArrayNotHasKey('documents.national_id', $errors);
    }

    public function test_a_draft_file_that_has_gone_missing_from_the_disk_is_reported_not_carried_silently(): void
    {
        $this->actingAsUser();
        $draft = $this->draft();
        $carried = $this->savedFile($draft, 'documents.national_id', 'my-id.pdf');
        Storage::disk('local')->delete($carried['documents.national_id']['path']);

        $check = new \ReflectionMethod(\App\Http\Controllers\StaffController::class, 'validateStaffUploads');
        $check->setAccessible(true);

        try {
            $check->invoke(app(\App\Http\Controllers\StaffController::class), \Illuminate\Http\Request::create('/', 'POST'), null, $carried);
            $this->fail('A missing file was carried on.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame('The file saved earlier could not be found. Upload it here instead.', $e->errors()['documents.national_id'][0]);
        }
    }

    // ── tidy-up ───────────────────────────────────────────────────────────

    public function test_discarding_a_draft_in_code_removes_its_folder_too(): void
    {
        $draft = $this->draft();
        $files = $this->savedFile($draft, 'documents.national_id', 'my-id.pdf');
        $draft->payload = ['fields' => [], 'files' => $files];
        $draft->save();

        $draft->discard();

        $this->assertSame(0, StaffDraft::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_old_drafts_command_shows_what_it_would_remove_and_removes_nothing_by_default(): void
    {
        $old = $this->draft(self::ME, ['full_names' => 'Old']);
        $old->forceFill(['updated_at' => now()->subDays(45)])->save();
        $this->draft(self::ME, ['full_names' => 'Recent']);

        $this->artisan('hr:purge-staff-drafts', ['--days' => 30])->expectsOutputToContain('1 draft')->expectsOutputToContain('Nothing was removed')->assertSuccessful();

        $this->assertSame(2, StaffDraft::count());
    }

    public function test_the_old_drafts_command_removes_drafts_not_touched_for_the_given_days_with_their_files(): void
    {
        $old = $this->draft(self::ME, ['full_names' => 'Old']);
        $files = $this->savedFile($old, 'documents.national_id', 'old-id.pdf');
        $old->payload = ['fields' => ['full_names' => 'Old'], 'files' => $files];
        $old->save();
        $old->forceFill(['updated_at' => now()->subDays(45)])->save();
        $recent = $this->draft(self::ME, ['full_names' => 'Recent']);
        $recent->forceFill(['updated_at' => now()->subDays(5)])->save();

        $this->artisan('hr:purge-staff-drafts', ['--days' => 30, '--apply' => true])->expectsOutputToContain('Removed 1')->assertSuccessful();

        $this->assertSame([$recent->id], StaffDraft::pluck('id')->all());
        $this->assertSame([], Storage::disk('local')->allFiles(), 'the old draft\'s files went with it');
    }
}

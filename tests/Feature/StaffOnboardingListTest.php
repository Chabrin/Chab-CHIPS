<?php

namespace Tests\Feature;

use App\Models\StaffDraft;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use App\Services\StaffOnboardingTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The Staff onboarding page: invitations and staff HR adds directly, in one list, each with how far its stages have got, what it is
 * waiting at, how long it has been open, and what to do next. Staff who are saved but have no contract yet stay in progress.
 */
class StaffOnboardingListTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const ME = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        Storage::fake('local');

        (require database_path('migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_make_onboarding_employment_type_nullable.php'))->up();
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        (require database_path('migrations/2026_11_11_100000_create_staff_drafts_table.php'))->up();
        (require database_path('migrations/2026_11_13_100000_add_onboarding_tracking_to_staff_drafts.php'))->up();
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('full_names')->nullable();
            $t->string('staff_number')->nullable();
            $t->string('employment_type')->nullable();
            $t->integer('position_id')->nullable();
        });
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) { $t->id(); $t->integer('staff_id'); });
        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); });
        DB::table('josya_users')->insert([['id' => self::ME, 'name' => 'Hannah HR'], ['id' => 8, 'name' => 'Colleague Two']]);

        $this->actingAsUser();
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actingAsUser(bool $allowed = true): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn($allowed);
        $user->shouldReceive('isAbleTo')->andReturn($allowed);
        $user->id = self::ME;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function invitation(string $status, array $extra = []): StaffOnboardingInvitation
    {
        static $n = 0;
        $n++;

        return StaffOnboardingInvitation::create($extra + [
            'recipient_email' => "person{$n}@example.test", 'token_hash' => hash('sha256', 'token' . $n), 'invited_by' => self::ME,
            'status' => $status, 'expires_at' => now()->addDays(3),
        ]);
    }

    private function draft(array $attributes = []): StaffDraft
    {
        return StaffDraft::create($attributes + ['created_by' => self::ME, 'title' => 'MARY WANJIKU', 'payload' => ['fields' => ['full_names' => 'Mary'], 'files' => []]]);
    }

    private function staff(string $name = 'Mary Wanjiku', ?int $position = 4, string $number = '121'): int
    {
        return DB::table('sys_staff')->insertGetId(['full_names' => $name, 'staff_number' => $number, 'employment_type' => 'Permanent', 'position_id' => $position]);
    }

    private function contractFor(int $staffId): void
    {
        DB::table('sys_hr_employee_contracts')->insert(['staff_id' => $staffId]);
    }

    private function rows()
    {
        return app(StaffOnboardingTracker::class)->rows();
    }

    private function row(string $key): array
    {
        return $this->rows()->firstWhere('key', $key) ?? $this->fail("No row {$key}");
    }

    private function statusesOf(array $row): array
    {
        return array_column($row['stages'], 'status', 'key');
    }

    // ── who can see it ────────────────────────────────────────────────────

    public function test_only_people_who_can_add_staff_see_the_page(): void
    {
        $this->actingAsUser(false);

        $this->get(route('staff.onboarding.index'))->assertForbidden();
    }

    public function test_the_page_has_the_two_ways_to_start(): void
    {
        $this->get(route('staff.onboarding.index'))->assertOk()
            ->assertSee('Staff onboarding')->assertSee(route('staff.create'), false)->assertSee(route('staff.onboarding.create'), false)
            ->assertSee('Nobody is being onboarded right now.');
    }

    // ── invitations ───────────────────────────────────────────────────────

    public function test_an_invitation_that_was_sent_waits_for_the_employee(): void
    {
        $invitation = $this->invitation('sent');

        $row = $this->row('invitation-' . $invitation->id);

        $this->assertSame('in_progress', $row['group']);
        $this->assertSame('Invitation', $row['source']);
        $this->assertSame('Link sent', $row['status_label']);
        $this->assertSame('Waiting for the employee to fill in the form', $row['now_at']);
        $this->assertSame($invitation->recipient_email, $row['name'], 'only the email is known until they submit');
        $this->assertSame('Hannah HR', $row['started_by']);
        $this->assertSame(['pending'], array_values(array_unique($this->statusesOf($row))));
        $this->assertSame(['personal', 'employment', 'contact', 'banking', 'documents', 'user', 'contract'], array_column($row['stages'], 'key'), 'the tabs, then the contract');
    }

    public function test_an_invitation_whose_link_has_run_out_says_so(): void
    {
        $invitation = $this->invitation('sent', ['expires_at' => now()->subDay()]);

        $this->assertSame('Link expired', $this->row('invitation-' . $invitation->id)['status_label']);
    }

    public function test_a_submitted_invitation_has_the_employees_stages_done_and_waits_for_review(): void
    {
        $invitation = $this->invitation('submitted');

        $row = $this->row('invitation-' . $invitation->id);

        $this->assertSame(['personal' => 'complete', 'employment' => 'pending', 'contact' => 'complete', 'banking' => 'complete', 'documents' => 'complete', 'user' => 'pending', 'contract' => 'pending'], $this->statusesOf($row));
        $this->assertSame('Waiting for HR to review what was submitted', $row['now_at']);
        $this->assertSame(['Review submission', 'Reject'], array_column($row['actions'], 'label'));
    }

    public function test_an_approved_submission_goes_on_to_add_staff_at_employment_details(): void
    {
        $invitation = $this->invitation('reviewed');

        $row = $this->row('invitation-' . $invitation->id);

        $this->assertSame('Employment Details', $row['now_at']);
        $this->assertSame('Continue to Add Staff', $row['actions'][0]['label']);
        $this->assertSame(route('staff.create', ['onboarding' => $invitation->id]), $row['actions'][0]['url']);
    }

    public function test_once_hr_has_a_draft_the_invitation_shows_its_progress_and_resumes_it_as_one_row(): void
    {
        $invitation = $this->invitation('reviewed');
        $draft = $this->draft(['onboarding_invitation_id' => $invitation->id, 'staff_number' => '121', 'employment_type' => 'Permanent', 'stages' => ['personal' => 'complete', 'employment' => 'complete', 'contact' => 'complete']]);

        $rows = $this->rows();
        $row = $rows->firstWhere('key', 'invitation-' . $invitation->id);

        $this->assertSame(1, $rows->count(), 'the draft is part of the invitation, not a second row');
        $this->assertSame('Banking & Statutory', $row['now_at']);
        $this->assertSame('MARY WANJIKU', $row['name']);
        $this->assertSame('121', $row['staff_number']);
        $this->assertSame('Resume draft', $row['actions'][0]['label']);
        $this->assertSame(route('staff.create', ['draft' => $draft->id]), $row['actions'][0]['url']);
    }

    public function test_cancelled_and_failed_invitations_are_closed_and_not_in_progress(): void
    {
        $revoked = $this->invitation('revoked');
        $failed = $this->invitation('mail_failed');

        $this->assertSame('closed', $this->row('invitation-' . $revoked->id)['group']);
        $this->assertSame('Cancelled', $this->row('invitation-' . $revoked->id)['status_label']);
        $this->assertSame('closed', $this->row('invitation-' . $failed->id)['group']);
        $this->assertSame('Email failed', $this->row('invitation-' . $failed->id)['status_label']);
        $this->assertSame([], $this->row('invitation-' . $failed->id)['actions']);
    }

    // ── staff added directly ──────────────────────────────────────────────

    public function test_a_draft_added_directly_is_a_row_with_its_stage_number_and_who_began_it(): void
    {
        $draft = $this->draft(['created_by' => 8, 'staff_number' => '122', 'employment_type' => 'Permanent', 'stages' => ['personal' => 'complete']]);

        $row = $this->row('draft-' . $draft->id);

        $this->assertSame('in_progress', $row['group']);
        $this->assertSame('Added by HR', $row['source']);
        $this->assertSame('Draft', $row['status_label']);
        $this->assertSame('MARY WANJIKU', $row['name']);
        $this->assertSame('Colleague Two', $row['started_by']);
        $this->assertSame('122', $row['staff_number']);
        $this->assertSame('Employment Details', $row['now_at'], 'the first stage not done');
        $this->assertSame(['Resume', 'Discard'], array_column($row['actions'], 'label'));
        $this->assertSame('delete', $row['actions'][1]['method']);
    }

    public function test_days_open_count_from_when_it_was_started(): void
    {
        $draft = $this->draft();
        DB::table('staff_drafts')->where('id', $draft->id)->update(['created_at' => now()->subDays(5)->subHours(3)]);
        $invitation = $this->invitation('sent');
        DB::table('staff_onboarding_invitations')->where('id', $invitation->id)->update(['created_at' => now()->subHours(2)]);

        $this->assertSame(5, $this->row('draft-' . $draft->id)['days_open']);
        $this->assertSame(0, $this->row('invitation-' . $invitation->id)['days_open']);
    }

    public function test_a_draft_with_nothing_typed_yet_has_a_name_to_show(): void
    {
        $draft = $this->draft(['title' => null]);

        $this->assertSame('Untitled (nothing typed yet)', $this->row('draft-' . $draft->id)['name']);
    }

    public function test_stages_that_do_not_apply_are_left_out(): void
    {
        $draft = $this->draft(['stages' => ['user' => 'skipped']]);

        $keys = array_column($this->row('draft-' . $draft->id)['stages'], 'key');

        $this->assertNotContains('user', $keys, 'casual staff get no system login');
        $this->assertContains('contract', $keys);
    }

    // ── saved as staff, waiting for the contract ──────────────────────────

    private function finishedDraft(int $staffId, array $extra = []): StaffDraft
    {
        return $this->draft($extra + ['staff_id' => $staffId, 'staff_number' => '121', 'employment_type' => 'Permanent', 'completed_at' => now(), 'stages' => StaffDraft::finishedStages(false), 'payload' => ['fields' => [], 'files' => []]]);
    }

    public function test_staff_who_are_saved_but_have_no_contract_stay_in_progress_at_add_contract(): void
    {
        $staffId = $this->staff('Mary Wanjiku', 4, '121');
        $draft = $this->finishedDraft($staffId);

        $row = $this->row('draft-' . $draft->id);

        $this->assertSame('in_progress', $row['group']);
        $this->assertSame('Staff created, no contract yet', $row['status_label']);
        $this->assertSame('Add contract', $row['now_at']);
        $this->assertSame('complete', $this->statusesOf($row)['user']);
        $this->assertSame('pending', $this->statusesOf($row)['contract']);
        $this->assertSame('Add contract', $row['actions'][0]['label']);
        $this->assertSame(route('contract.create', ['staff_id' => $staffId, 'position_id' => 4]), $row['actions'][0]['url'], 'with the person and their position chosen');
    }

    public function test_once_they_have_a_contract_it_is_finished(): void
    {
        $staffId = $this->staff();
        $draft = $this->finishedDraft($staffId);
        $this->contractFor($staffId);

        $row = $this->row('draft-' . $draft->id);

        $this->assertSame('finished', $row['group']);
        $this->assertSame('complete', $this->statusesOf($row)['contract']);
        $this->assertSame('All done', $row['now_at']);
        $this->assertNotNull($row['finished_at']);
        $this->assertSame('View staff', $row['actions'][0]['label']);
    }

    public function test_an_approved_invitation_follows_its_staff_member_to_the_contract_too(): void
    {
        $staffId = $this->staff();
        $invitation = $this->invitation('approved', ['staff_id' => $staffId]);

        $this->assertSame('in_progress', $this->row('invitation-' . $invitation->id)['group']);
        $this->assertSame('Add contract', $this->row('invitation-' . $invitation->id)['now_at']);
        $this->assertSame('Mary Wanjiku', $this->row('invitation-' . $invitation->id)['name'], 'the name is known once they are staff');

        $this->contractFor($staffId);

        $this->assertSame('finished', $this->row('invitation-' . $invitation->id)['group']);
    }

    public function test_an_older_approved_invitation_with_no_staff_link_is_simply_finished(): void
    {
        $invitation = $this->invitation('approved');

        $this->assertSame('finished', $this->row('invitation-' . $invitation->id)['group']);
    }

    public function test_a_finished_record_of_an_invitation_is_one_row_with_the_invitation(): void
    {
        $staffId = $this->staff();
        $invitation = $this->invitation('approved', ['staff_id' => $staffId]);
        $this->finishedDraft($staffId, ['onboarding_invitation_id' => $invitation->id]);

        $this->assertSame(1, $this->rows()->count());
    }

    // ── the page ──────────────────────────────────────────────────────────

    public function test_the_page_shows_each_row_with_its_stage_marks_and_actions(): void
    {
        $draft = $this->draft(['staff_number' => '121', 'employment_type' => 'Permanent', 'stages' => ['personal' => 'complete', 'employment' => 'problem']]);
        $invitation = $this->invitation('sent');

        $page = $this->get(route('staff.onboarding.index'))->assertOk()->getContent();

        $this->assertStringContainsString('MARY WANJIKU', $page);
        $this->assertStringContainsString($invitation->recipient_email, $page);
        $this->assertStringContainsString('Personal Information: complete', $page);
        $this->assertStringContainsString('Employment Details: has a problem', $page);
        $this->assertStringContainsString('Add contract: not done yet', $page);
        $this->assertStringContainsString('Added by HR', $page);
        $this->assertStringContainsString('Waiting for the employee to fill in the form', $page);
        $this->assertStringContainsString(route('staff.create', ['draft' => $draft->id]), $page);
        $this->assertStringContainsString(route('staff.drafts.destroy', $draft->id), $page);
        $this->assertStringContainsString(route('staff.onboarding.revoke', $invitation), $page);
        $this->assertStringContainsString('Days open', $page);
    }

    public function test_the_page_says_a_person_waits_at_add_contract_with_a_button_to_it(): void
    {
        $staffId = $this->staff('Mary Wanjiku', 4);
        $this->finishedDraft($staffId);

        $page = $this->get(route('staff.onboarding.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Staff created, no contract yet', $page);
        $this->assertStringContainsString('href="' . e(route('contract.create', ['staff_id' => $staffId, 'position_id' => 4])) . '"', $page);
    }

    public function test_the_tabs_count_each_group_and_show_only_that_group(): void
    {
        $this->invitation('sent');
        $this->draft();
        $this->invitation('revoked');
        $doneStaff = $this->staff('Done Person', 4, '120');
        $this->finishedDraft($doneStaff, ['title' => 'DONE PERSON']);
        $this->contractFor($doneStaff);

        $this->assertSame(['in_progress' => 2, 'finished' => 1, 'closed' => 1], StaffOnboardingTracker::counts($this->rows()));

        $progress = $this->get(route('staff.onboarding.index'))->assertOk()->getContent();
        $this->assertStringContainsString('MARY WANJIKU', $progress);
        $this->assertStringNotContainsString('DONE PERSON', $progress);

        $finished = $this->get(route('staff.onboarding.index', ['show' => 'finished']))->assertOk()->getContent();
        $this->assertStringContainsString('DONE PERSON', $finished);
        $this->assertStringNotContainsString('MARY WANJIKU', $finished);

        $this->get(route('staff.onboarding.index', ['show' => 'closed']))->assertOk()->assertSee('Cancelled');
    }

    public function test_an_unknown_tab_falls_back_to_in_progress(): void
    {
        $this->draft();

        $this->get(route('staff.onboarding.index', ['show' => 'nonsense']))->assertOk()->assertSee('MARY WANJIKU');
    }

    public function test_what_has_been_open_longest_comes_first_among_those_in_progress(): void
    {
        $newer = $this->draft(['title' => 'NEWER ONE']);
        $older = $this->draft(['title' => 'OLDER ONE']);
        DB::table('staff_drafts')->where('id', $older->id)->update(['created_at' => now()->subDays(9)]);

        $titles = StaffOnboardingTracker::inGroup($this->rows(), 'in_progress')->pluck('name')->all();

        $this->assertSame(['OLDER ONE', 'NEWER ONE'], $titles);
    }

    public function test_the_list_is_paged(): void
    {
        for ($i = 1; $i <= 27; $i++) {
            $this->draft(['title' => 'PERSON ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $first = $this->get(route('staff.onboarding.index'))->assertOk()->getContent();
        $second = $this->get(route('staff.onboarding.index', ['page' => 2]))->assertOk()->getContent();

        $this->assertSame(25, substr_count($first, 'data-onboarding="draft-'));
        $this->assertSame(2, substr_count($second, 'data-onboarding="draft-'));
    }

    public function test_the_list_does_not_read_the_encrypted_contents_of_anything(): void
    {
        $invitation = $this->invitation('submitted', ['submission' => ['full_names' => 'Secret Name', 'national_id' => '99999999']]);
        $this->draft(['payload' => ['fields' => ['national_id' => '88888888'], 'files' => []]]);

        $page = $this->get(route('staff.onboarding.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Secret Name', $page);
        $this->assertStringNotContainsString('99999999', $page);
        $this->assertStringNotContainsString('88888888', $page);
        $this->assertSame($invitation->recipient_email, $this->row('invitation-' . $invitation->id)['name']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffDraft;
use App\Models\User;
use App\Services\StaffNumbers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staff numbers held for drafts. Two people adding staff at the same time must never be given the same number; a draft that goes
 * gives its number back and the drafts after it close the gap; a draft that becomes a staff member keeps its number and shifts nobody.
 */
class StaffNumberReservationTest extends TestCase
{
    private const ME = 7;
    private const SOMEONE_ELSE = 8;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        (require database_path('migrations/2026_11_11_100000_create_staff_drafts_table.php'))->up();
        (require database_path('migrations/2026_11_13_100000_add_onboarding_tracking_to_staff_drafts.php'))->up();
        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('employment_type')->nullable();
            $t->string('national_id')->nullable();
        });
    }

    private function actingAsUser(int $id = self::ME): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->shouldReceive('isAbleTo')->andReturn(true);
        $user->id = $id;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    /** Save a draft as a user, the way the form does, and give back what is stored. */
    private function saveDraft(array $fields = [], int $user = self::ME): StaffDraft
    {
        $this->actingAsUser($user);
        $id = $this->call('POST', route('staff.drafts.save'), $fields + ['full_names' => 'Draft Person', 'employment_type' => 'Permanent'], [], [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk()->json('draft_id');

        return StaffDraft::findOrFail($id);
    }

    private function discard(StaffDraft $draft, int $user = self::ME): void
    {
        $this->actingAsUser($user);
        $this->deleteJson(route('staff.drafts.destroy', $draft->id))->assertOk();
    }

    private function existingStaff(string $number, string $type = 'Permanent'): void
    {
        DB::table('sys_staff')->insert(['staff_number' => $number, 'employment_type' => $type, 'national_id' => 'existing-' . $type . $number]);
    }

    private function numbers(StaffDraft ...$drafts): array
    {
        return array_map(fn (StaffDraft $draft) => $draft->fresh()->staff_number, $drafts);
    }

    // ── holding a number ──────────────────────────────────────────────────

    public function test_the_first_save_holds_the_next_number_after_the_highest_staff_number(): void
    {
        $this->existingStaff('119');
        $this->existingStaff('120');
        $this->actingAsUser();

        $response = $this->call('POST', route('staff.drafts.save'), ['full_names' => 'Mary', 'employment_type' => 'Permanent'], [], [], ['HTTP_ACCEPT' => 'application/json'])->assertOk();

        $this->assertSame('121', $response->json('staff_number'));
        $draft = StaffDraft::firstOrFail();
        $this->assertSame('121', $draft->staff_number);
        $this->assertSame('Permanent', $draft->employment_type);
    }

    public function test_with_no_staff_yet_the_first_number_is_one(): void
    {
        $this->assertSame('1', $this->saveDraft()->staff_number);
        $this->assertSame('CT-001', $this->saveDraft(['employment_type' => 'Contract'])->staff_number);
    }

    public function test_every_draft_gets_its_own_number_whoever_started_it(): void
    {
        $this->existingStaff('120');

        $mine = $this->saveDraft([], self::ME);
        $theirs = $this->saveDraft([], self::SOMEONE_ELSE);
        $another = $this->saveDraft([], self::ME);

        $this->assertSame(['121', '122', '123'], $this->numbers($mine, $theirs, $another));
    }

    public function test_saving_a_draft_again_keeps_its_number(): void
    {
        $this->existingStaff('120');
        $draft = $this->saveDraft();
        $this->saveDraft(['full_names' => 'Someone Else'], self::SOMEONE_ELSE);

        $this->actingAsUser();
        $this->call('POST', route('staff.drafts.save'), ['draft_id' => $draft->id, 'full_names' => 'Mary Wanjiku', 'employment_type' => 'Permanent'], [], [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk()->assertJsonPath('staff_number', '121');

        $this->assertSame('121', $draft->fresh()->staff_number);
    }

    public function test_the_next_number_offered_to_anyone_skips_the_numbers_drafts_hold(): void
    {
        $this->existingStaff('120');
        $this->assertSame('121', Staff::nextStaffNumber('Permanent'));

        $this->saveDraft();
        $this->saveDraft();

        $this->assertSame('123', Staff::nextStaffNumber('Permanent'));
    }

    public function test_categories_have_their_own_runs(): void
    {
        $this->existingStaff('120');
        $this->existingStaff('CT-004', 'Contract');

        $permanent = $this->saveDraft();
        $contract = $this->saveDraft(['employment_type' => 'Contract']);
        $secondContract = $this->saveDraft(['employment_type' => 'Contract']);
        $casual = $this->saveDraft(['employment_type' => 'Casual']);

        $this->assertSame(['121', 'CT-005', 'CT-006', 'C-001'], $this->numbers($permanent, $contract, $secondContract, $casual));
    }

    public function test_a_category_that_is_not_one_of_the_choices_falls_back_to_the_default(): void
    {
        $draft = $this->saveDraft(['employment_type' => 'Whatever I typed']);

        $this->assertSame('Permanent', $draft->employment_type);
        $this->assertSame('1', $draft->staff_number);
    }

    public function test_a_draft_saved_without_choosing_a_category_is_held_in_the_default_one(): void
    {
        $this->actingAsUser();
        $id = $this->call('POST', route('staff.drafts.save'), ['full_names' => 'Mary'], [], [], ['HTTP_ACCEPT' => 'application/json'])->assertOk()->json('draft_id');

        $this->assertSame('Permanent', StaffDraft::find($id)->employment_type);
    }

    public function test_the_list_of_drafts_shows_each_number(): void
    {
        $this->existingStaff('120');
        $this->saveDraft();

        $this->actingAsUser();
        $this->getJson(route('staff.drafts.index'))->assertOk()->assertJsonPath('drafts.0.staff_number', '121');
    }

    public function test_numbers_of_finished_records_are_not_held(): void
    {
        $this->existingStaff('120');
        StaffDraft::create(['created_by' => self::ME, 'payload' => ['fields' => [], 'files' => []], 'employment_type' => 'Permanent', 'staff_number' => '150', 'completed_at' => now()]);

        $this->assertSame('121', Staff::nextStaffNumber('Permanent'), 'the staff member it became is counted through the staff list, not through the record');
        $this->assertSame('121', $this->saveDraft()->staff_number);
    }

    // ── the number lookup on the form ─────────────────────────────────────

    public function test_the_lookup_gives_the_next_free_number_and_says_it_is_not_held(): void
    {
        $this->existingStaff('120');
        $this->saveDraft();

        $this->actingAsUser();
        $this->getJson(route('staff.get-staff-number', ['category' => 'Permanent']))
            ->assertOk()->assertJsonPath('staff_number', '122')->assertJsonPath('reserved', false);
    }

    public function test_the_lookup_for_a_form_opened_from_a_draft_gives_the_number_the_draft_holds(): void
    {
        $this->existingStaff('120');
        $draft = $this->saveDraft();
        $this->saveDraft();

        $this->actingAsUser();
        $this->getJson(route('staff.get-staff-number', ['category' => 'Permanent', 'draft_id' => $draft->id]))
            ->assertOk()->assertJsonPath('staff_number', '121')->assertJsonPath('reserved', true);
    }

    public function test_the_lookup_for_a_draft_in_another_category_is_just_the_next_free_number_there(): void
    {
        $draft = $this->saveDraft();

        $this->actingAsUser();
        $this->getJson(route('staff.get-staff-number', ['category' => 'Contract', 'draft_id' => $draft->id]))
            ->assertOk()->assertJsonPath('staff_number', 'CT-001')->assertJsonPath('reserved', false);
    }

    public function test_a_colleague_carrying_on_someone_elses_draft_is_given_its_number(): void
    {
        $this->existingStaff('120');
        $theirs = $this->saveDraft([], self::SOMEONE_ELSE);

        $this->actingAsUser(self::ME);
        $this->getJson(route('staff.get-staff-number', ['category' => 'Permanent', 'draft_id' => $theirs->id]))
            ->assertOk()->assertJsonPath('staff_number', '121')->assertJsonPath('reserved', true);
    }

    public function test_a_finished_record_does_not_give_a_form_its_number(): void
    {
        $this->existingStaff('120');
        $finished = StaffDraft::create(['created_by' => self::ME, 'payload' => ['fields' => [], 'files' => []], 'employment_type' => 'Permanent', 'staff_number' => '120', 'completed_at' => now()]);

        $this->actingAsUser();
        $this->getJson(route('staff.get-staff-number', ['category' => 'Permanent', 'draft_id' => $finished->id]))
            ->assertOk()->assertJsonPath('staff_number', '121')->assertJsonPath('reserved', false);
    }

    // ── a draft goes, and the numbers close up ────────────────────────────

    public function test_discarding_the_first_draft_moves_the_others_down(): void
    {
        $this->existingStaff('120');
        [$a, $b, $c] = [$this->saveDraft(), $this->saveDraft(), $this->saveDraft()];

        $this->discard($a);

        $this->assertSame(['121', '122'], $this->numbers($b, $c), 'the remaining draft takes the number of the one that went');
        $this->assertSame('123', Staff::nextStaffNumber('Permanent'), 'and the freed number is the last of the run');
    }

    public function test_discarding_a_draft_in_the_middle_moves_only_the_ones_after_it(): void
    {
        $this->existingStaff('120');
        [$a, $b, $c] = [$this->saveDraft(), $this->saveDraft(), $this->saveDraft()];

        $this->discard($b);

        $this->assertSame(['121', '122'], $this->numbers($a, $c));
    }

    public function test_discarding_the_last_draft_moves_nobody(): void
    {
        $this->existingStaff('120');
        [$a, $b, $c] = [$this->saveDraft(), $this->saveDraft(), $this->saveDraft()];

        $this->discard($c);

        $this->assertSame(['121', '122'], $this->numbers($a, $b));
        $this->assertSame('123', Staff::nextStaffNumber('Permanent'));
    }

    public function test_discarding_the_only_draft_gives_its_number_back(): void
    {
        $this->existingStaff('120');
        $draft = $this->saveDraft();
        $this->assertSame('122', Staff::nextStaffNumber('Permanent'));

        $this->discard($draft);

        $this->assertSame('121', Staff::nextStaffNumber('Permanent'));
    }

    public function test_the_next_new_draft_takes_the_number_that_was_freed(): void
    {
        $this->existingStaff('120');
        [$a, $b] = [$this->saveDraft(), $this->saveDraft()];
        $this->discard($a);

        $new = $this->saveDraft();

        $this->assertSame(['121', '122'], $this->numbers($b, $new), 'no number is ever held twice');
    }

    public function test_drafts_close_up_whoever_started_them(): void
    {
        $this->existingStaff('120');
        $mine = $this->saveDraft([], self::ME);
        $theirs = $this->saveDraft([], self::SOMEONE_ELSE);

        $this->discard($mine, self::ME);

        $this->assertSame('121', $theirs->fresh()->staff_number);
    }

    public function test_discarding_in_one_category_leaves_the_others_alone(): void
    {
        $permanent = $this->saveDraft();
        $firstContract = $this->saveDraft(['employment_type' => 'Contract']);
        $secondContract = $this->saveDraft(['employment_type' => 'Contract']);

        $this->discard($permanent);

        $this->assertSame(['CT-001', 'CT-002'], $this->numbers($firstContract, $secondContract));

        $this->discard($firstContract);
        $this->assertSame('CT-001', $secondContract->fresh()->staff_number);
    }

    public function test_numbers_moving_is_not_the_person_saving_so_last_saved_stays(): void
    {
        $this->existingStaff('120');
        $a = $this->saveDraft();
        $b = $this->saveDraft();
        DB::table('staff_drafts')->where('id', $b->id)->update(['updated_at' => '2026-11-01 09:00:00']);

        $this->discard($a);

        $this->assertSame('121', $b->fresh()->staff_number);
        $this->assertSame('2026-11-01 09:00:00', $b->fresh()->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_drafts_removed_by_the_purge_command_close_up_too(): void
    {
        $this->existingStaff('120');
        [$old, $recent] = [$this->saveDraft(), $this->saveDraft()];
        DB::table('staff_drafts')->where('id', $old->id)->update(['updated_at' => now()->subDays(60)]);

        $this->artisan('hr:purge-staff-drafts', ['--days' => 30, '--apply' => true])->assertSuccessful();

        $this->assertNull(StaffDraft::find($old->id));
        $this->assertSame('121', $recent->fresh()->staff_number);
    }

    public function test_changing_the_category_of_a_draft_gives_the_old_number_back_and_holds_one_in_the_new_category(): void
    {
        $this->existingStaff('120');
        [$a, $b] = [$this->saveDraft(), $this->saveDraft()];

        $this->actingAsUser();
        $this->call('POST', route('staff.drafts.save'), ['draft_id' => $a->id, 'full_names' => 'Mary', 'employment_type' => 'Contract'], [], [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk()->assertJsonPath('staff_number', 'CT-001');

        $this->assertSame('Contract', $a->fresh()->employment_type);
        $this->assertSame('121', $b->fresh()->staff_number, 'the draft left in the old category closes the gap');
    }

    public function test_a_discarded_draft_that_held_no_number_changes_nothing(): void
    {
        $this->existingStaff('120');
        $held = $this->saveDraft();
        $none = StaffDraft::create(['created_by' => self::ME, 'payload' => ['fields' => [], 'files' => []]]);

        $none->discard();

        $this->assertSame('121', $held->fresh()->staff_number);
    }

    // ── the pieces ────────────────────────────────────────────────────────

    public function test_the_number_in_a_staff_number_and_how_one_is_written(): void
    {
        $this->assertSame(7, Staff::numberPart('CT-007'));
        $this->assertSame(281, Staff::numberPart('281'));
        $this->assertSame(0, Staff::numberPart(null));
        $this->assertSame('281', Staff::formatStaffNumber('Permanent', 281));
        $this->assertSame('CT-007', Staff::formatStaffNumber('Contract', 7));
        $this->assertSame('1234', Staff::formatStaffNumber('Nonexistent', 1234), 'a code with no category gets plain numbers');
        $this->assertSame('CT-1234', Staff::formatStaffNumber('Contract', 1234), 'more than three digits are not cut');
    }

    public function test_the_lock_can_be_asked_for_again_inside_itself_and_is_given_up_after_a_failure(): void
    {
        $this->assertSame('done', StaffNumbers::locked(fn () => StaffNumbers::locked(fn () => 'done')));

        try {
            StaffNumbers::locked(fn () => throw new \RuntimeException('boom'));
            $this->fail('the failure should come out');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('again', StaffNumbers::locked(fn () => 'again'));
    }

    public function test_the_next_number_is_still_given_when_the_drafts_table_is_not_there(): void
    {
        $this->existingStaff('120');
        Schema::drop('staff_drafts');

        $this->assertSame('121', Staff::nextStaffNumber('Permanent'));
    }

    public function test_the_number_for_a_new_staff_member_is_the_draft_number_unless_taken_or_in_another_category(): void
    {
        $this->existingStaff('120');
        $draft = $this->saveDraft();

        $this->assertSame('121', StaffNumbers::numberForNewStaff('Permanent', $draft));
        $this->assertSame('CT-001', StaffNumbers::numberForNewStaff('Contract', $draft), 'another category: the next one there');
        $this->assertSame('122', StaffNumbers::numberForNewStaff('Permanent', null), 'no draft: the next one nobody holds');

        $this->existingStaff('121');
        $this->assertSame('122', StaffNumbers::numberForNewStaff('Permanent', $draft->fresh()), 'taken meanwhile');
    }
}

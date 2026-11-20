<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\ScheduledDeduction;
use App\Models\ScheduledDeductionEvent;
use App\Models\User;
use App\Services\ScheduledDeductions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * Scheduled deductions (UAT A6, slice 1): making one with its month-by-month schedule, the signed consent form that releases it,
 * pausing, closing early, changing the monthly amount, the types HR can add, and who may do what.
 */
class ScheduledDeductionsTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const STAFF = 1;
    private const ME = 7;
    private const SALARY_ADVANCE = 2;

    private const EVERYTHING = [
        'read_scheduled_deductions', 'create_scheduled_deductions', 'update_scheduled_deductions',
        'close_scheduled_deductions', 'manage_deduction_types', 'override_deduction_limits',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // The page layout is replaced by a plain one that also prints the page's modals, so the whole page is rendered and checked
        $this->usePlainLayout(true);
        File::put($this->plainLayoutFolder . '/layouts/main.blade.php', "@yield('styles')@yield('content')@yield('modals')@yield('scripts')");

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-20 09:00:00'));

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('full_names')->nullable();
            $t->integer('current_status_id')->default(1);
        });
        Schema::create('josya_users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('sys_helb_deductions', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id');
            $t->decimal('loan_balance', 12, 2)->default(0);
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
        });
        (require database_path('migrations/2026_11_20_100000_create_scheduled_deduction_tables.php'))->up();


        DB::table('sys_staff')->insert([
            ['id' => self::STAFF, 'staff_number' => 'E-001', 'full_names' => 'ALICE WANJIRU', 'current_status_id' => 1],
            ['id' => 2, 'staff_number' => 'E-002', 'full_names' => 'BOB OTIENO', 'current_status_id' => 3],
        ]);
        DB::table('josya_users')->insert(['id' => self::ME, 'name' => 'Hannah HR']);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actingWith(array $permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = self::ME;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function actingAsHr(): void
    {
        $this->actingWith(self::EVERYTHING);
    }

    private function pdf(string $name = 'consent.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    private function save(array $over = [])
    {
        return $this->post(route('scheduled-deductions.store'), array_merge([
            'staff_id' => self::STAFF, 'type_id' => self::SALARY_ADVANCE, 'total_amount' => '21500',
            'plan_by' => 'monthly', 'monthly_amount' => '2000', 'start_month' => '2026-12',
        ], $over));
    }

    private function made(array $over = [], ?UploadedFile $consent = null): ScheduledDeduction
    {
        $this->actingAsHr();
        $this->save($over + ($consent ? ['consent' => $consent] : []))->assertSessionHasNoErrors();

        return ScheduledDeduction::latest('id')->firstOrFail();
    }

    private function id(ScheduledDeduction $deduction): string
    {
        return Crypt::encrypt($deduction->id);
    }

    // ── the types ───────────────────────────────────────────────────────

    public function test_the_starting_types_are_there_and_only_the_salary_advance_is_a_company_loan(): void
    {
        $this->assertSame(['Sacco / Akiba loan', 'Salary advance', 'Loss / damage recovery', 'Other'], DeductionType::orderBy('id')->pluck('name')->all(), 'HELB is not one: it has its own screen');
        $this->assertSame(['Salary advance'], DeductionType::where('is_company_loan', true)->pluck('name')->all());
    }

    public function test_a_new_type_can_be_added_and_then_chosen(): void
    {
        $this->actingAsHr();

        $this->post(route('scheduled-deductions.types.store'), ['name' => 'Staff mortgage', 'is_company_loan' => '1'])->assertSessionHasNoErrors();

        $type = DeductionType::where('name', 'Staff mortgage')->firstOrFail();
        $this->assertTrue($type->is_company_loan);
        $this->save(['type_id' => $type->id])->assertSessionHasNoErrors();
        $this->assertSame($type->id, ScheduledDeduction::firstOrFail()->type_id);
    }

    public function test_a_type_name_cannot_be_used_twice(): void
    {
        $this->actingAsHr();

        $this->post(route('scheduled-deductions.types.store'), ['name' => 'Other'])->assertSessionHasErrors('name');
    }

    public function test_a_hidden_type_is_not_offered_and_cannot_be_used(): void
    {
        $this->actingAsHr();

        $this->post(route('scheduled-deductions.types.toggle', 4))->assertRedirect();

        $this->assertFalse(DeductionType::find(4)->is_active);
        $this->assertStringNotContainsString('<option value="4"', $this->get(route('scheduled-deductions.create'))->assertOk()->getContent());
        $this->save(['type_id' => 4])->assertSessionHasErrors('type_id');
        $this->assertSame(0, ScheduledDeduction::count());
    }

    // ── making one ──────────────────────────────────────────────────────

    public function test_a_deduction_without_the_signed_form_is_on_hold_with_its_schedule_worked_out(): void
    {
        $this->actingAsHr();

        $this->save()->assertSessionHasNoErrors()->assertRedirect();

        $deduction = ScheduledDeduction::firstOrFail();
        $this->assertSame(ScheduledDeduction::ON_HOLD, $deduction->status);
        $this->assertSame(11, $deduction->months, 'KES 21,500 at 2,000 a month');
        $this->assertSame('2026-12', $deduction->start_month->format('Y-m'));
        $this->assertSame('2027-10', $deduction->end_month->format('Y-m'));
        $this->assertEquals(21500, $deduction->balance);
        $this->assertEquals(2000, $deduction->monthly_amount);
        $this->assertSame(self::ME, (int) $deduction->created_by);
        $this->assertSame(['created'], $deduction->events()->pluck('event')->all());
    }

    public function test_a_deduction_made_with_the_signed_form_is_active_and_the_form_is_kept(): void
    {
        $deduction = $this->made([], $this->pdf('alice-consent.pdf'));

        $this->assertSame(ScheduledDeduction::ACTIVE, $deduction->status);
        $this->assertSame('alice-consent.pdf', $deduction->consent_name);
        Storage::disk('local')->assertExists($deduction->consent_path);
        $this->assertSame(['created', 'consent_added'], $deduction->events()->orderBy('id')->pluck('event')->all());
    }

    public function test_the_months_can_be_given_and_the_monthly_amount_is_worked_out(): void
    {
        $this->actingAsHr();

        $this->save(['total_amount' => '12000', 'plan_by' => 'months', 'months' => '6', 'monthly_amount' => ''])->assertSessionHasNoErrors();

        $deduction = ScheduledDeduction::firstOrFail();
        $this->assertEquals(2000, $deduction->monthly_amount);
        $this->assertSame(6, $deduction->months);
        $this->assertSame('2027-05', $deduction->end_month->format('Y-m'));
    }

    public function test_the_end_month_can_be_given_and_the_months_are_worked_out(): void
    {
        $this->actingAsHr();

        $this->save(['total_amount' => '10000', 'plan_by' => 'end', 'end_month' => '2027-03', 'monthly_amount' => ''])->assertSessionHasNoErrors();

        $deduction = ScheduledDeduction::firstOrFail();
        $this->assertSame(4, $deduction->months, 'Dec, Jan, Feb, Mar');
        $this->assertEquals(2500, $deduction->monthly_amount);
    }

    public function test_the_value_the_plan_is_built_from_must_be_given(): void
    {
        $this->actingAsHr();

        $this->save(['plan_by' => 'months', 'months' => '', 'monthly_amount' => ''])->assertSessionHasErrors('months');
        $this->save(['plan_by' => 'end', 'end_month' => '', 'monthly_amount' => ''])->assertSessionHasErrors('end_month');
        $this->save(['plan_by' => 'monthly', 'monthly_amount' => ''])->assertSessionHasErrors('monthly_amount');
        $this->assertSame(0, ScheduledDeduction::count());
    }

    public function test_a_plan_that_cannot_work_is_refused_with_the_reason(): void
    {
        $this->actingAsHr();

        $this->save(['total_amount' => '10000', 'plan_by' => 'end', 'end_month' => '2026-10', 'monthly_amount' => ''])->assertSessionHasErrors('schedule');
        $this->save(['total_amount' => '5000000', 'monthly_amount' => '10'])->assertSessionHasErrors('schedule');
        $this->assertSame(0, ScheduledDeduction::count());
    }

    public function test_the_signed_form_must_be_a_pdf(): void
    {
        $this->actingAsHr();

        $this->save(['consent' => UploadedFile::fake()->image('photo.png')])->assertSessionHasErrors('consent');
        $this->assertSame(0, ScheduledDeduction::count());
    }

    public function test_a_terminated_staff_member_cannot_be_given_a_deduction_and_is_not_offered(): void
    {
        $this->actingAsHr();

        $this->assertStringNotContainsString('BOB OTIENO', $this->get(route('scheduled-deductions.create'))->assertOk()->getContent());
        $this->assertStringContainsString('ALICE WANJIRU', $this->get(route('scheduled-deductions.create'))->getContent());
    }

    public function test_helb_is_not_a_scheduled_deduction_because_it_has_its_own_screen(): void
    {
        $this->actingAsHr();

        foreach (['HELB', 'helb loan', 'Staff HELB top-up'] as $name) {
            $this->post(route('scheduled-deductions.types.store'), ['name' => $name])->assertSessionHasErrors(['name' => 'HELB is deducted by law and has its own screen: use HELB Loans.']);
        }
        $this->assertNull(DeductionType::where('name', 'like', '%helb%')->first());
        $page = $this->get(route('scheduled-deductions.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>HELB</option>', $page);
        $this->assertStringContainsString('HELB is not here', $page, 'the form points to HELB Loans instead');
    }

    // ── the consent form ────────────────────────────────────────────────

    public function test_adding_the_signed_form_later_releases_a_deduction_that_was_on_hold(): void
    {
        $deduction = $this->made();

        $this->post(route('scheduled-deductions.consent.store', $this->id($deduction)), ['consent' => $this->pdf()])->assertSessionHasNoErrors();

        $deduction->refresh();
        $this->assertSame(ScheduledDeduction::ACTIVE, $deduction->status);
        $this->assertSame(self::ME, (int) $deduction->consent_by);
        $this->assertContains('consent_added', $deduction->events()->pluck('event')->all());
    }

    public function test_a_replaced_form_keeps_the_earlier_file_and_says_so_in_the_history(): void
    {
        $deduction = $this->made([], $this->pdf('first.pdf'));
        $first = $deduction->consent_path;

        $this->post(route('scheduled-deductions.consent.store', $this->id($deduction)), ['consent' => $this->pdf('second.pdf')])->assertSessionHasNoErrors();

        $deduction->refresh();
        $this->assertNotSame($first, $deduction->consent_path);
        $this->assertSame('second.pdf', $deduction->consent_name);
        Storage::disk('local')->assertExists($first);
        Storage::disk('local')->assertExists($deduction->consent_path);
        $replaced = $deduction->events()->where('event', 'consent_replaced')->firstOrFail();
        $this->assertStringContainsString(basename($first), $replaced->details);
    }

    public function test_the_form_can_be_opened_and_a_missing_one_is_not_found(): void
    {
        $with = $this->made([], $this->pdf());
        $without = $this->made(['description' => 'second']);

        $this->get(route('scheduled-deductions.consent.download', $this->id($with)))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('scheduled-deductions.consent.download', $this->id($without)))->assertNotFound();
    }

    public function test_a_pasted_or_changed_link_is_not_found(): void
    {
        $this->actingAsHr();

        $this->get(route('scheduled-deductions.show', 'not-a-real-id'))->assertNotFound();
    }

    // ── pause, resume, close ────────────────────────────────────────────

    public function test_pausing_needs_a_reason_and_stops_an_active_deduction(): void
    {
        $deduction = $this->made([], $this->pdf());

        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => 'abc'])->assertSessionHasErrors('reason');
        $this->assertSame(ScheduledDeduction::ACTIVE, $deduction->fresh()->status);

        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => 'On unpaid leave for two months'])->assertSessionHasNoErrors();

        $deduction->refresh();
        $this->assertSame(ScheduledDeduction::PAUSED, $deduction->status);
        $this->assertSame('On unpaid leave for two months', $deduction->pause_reason);
        $this->assertSame('On unpaid leave for two months', $deduction->events()->where('event', 'paused')->firstOrFail()->reason);
    }

    public function test_only_an_active_deduction_can_be_paused(): void
    {
        $onHold = $this->made();

        $this->post(route('scheduled-deductions.pause', $this->id($onHold)), ['reason' => 'a good enough reason'])->assertSessionHasErrors('reason');
        $this->assertSame(ScheduledDeduction::ON_HOLD, $onHold->fresh()->status);
    }

    public function test_resuming_a_paused_deduction_makes_it_active_again(): void
    {
        $deduction = $this->made([], $this->pdf());
        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => 'On unpaid leave']);

        $this->post(route('scheduled-deductions.resume', $this->id($deduction)))->assertSessionHasNoErrors();

        $deduction->refresh();
        $this->assertSame(ScheduledDeduction::ACTIVE, $deduction->status);
        $this->assertNull($deduction->pause_reason);
        $this->assertContains('resumed', $deduction->events()->pluck('event')->all());
    }

    public function test_an_active_deduction_cannot_be_resumed(): void
    {
        $deduction = $this->made([], $this->pdf());

        $this->post(route('scheduled-deductions.resume', $this->id($deduction)))->assertSessionHasErrors('reason');
    }

    public function test_closing_early_needs_a_reason_and_keeps_what_was_still_owed(): void
    {
        $deduction = $this->made([], $this->pdf());

        $this->post(route('scheduled-deductions.close', $this->id($deduction)), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(ScheduledDeduction::ACTIVE, $deduction->fresh()->status);

        $this->post(route('scheduled-deductions.close', $this->id($deduction)), ['reason' => 'Employee cleared the balance in cash'])->assertSessionHasNoErrors();

        $deduction->refresh();
        $this->assertSame(ScheduledDeduction::CLOSED, $deduction->status);
        $this->assertEquals(21500, $deduction->balance);
        $this->assertSame(self::ME, (int) $deduction->closed_by);
        $this->assertSame('Employee cleared the balance in cash', $deduction->close_reason);
        $this->assertNotNull($deduction->closed_at);
    }

    public function test_a_closed_deduction_cannot_be_changed_any_more(): void
    {
        $deduction = $this->made([], $this->pdf());
        $this->post(route('scheduled-deductions.close', $this->id($deduction)), ['reason' => 'Closed by agreement']);

        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => 'another reason'])->assertSessionHasErrors('reason');
        $this->post(route('scheduled-deductions.monthly', $this->id($deduction)), ['monthly_amount' => '1000', 'reason' => 'another reason'])->assertSessionHasErrors('reason');
        $this->post(route('scheduled-deductions.consent.store', $this->id($deduction)), ['consent' => $this->pdf('late.pdf')])->assertSessionHasErrors('reason');
        $this->assertSame(ScheduledDeduction::CLOSED, $deduction->fresh()->status);
        $this->assertEquals(2000, $deduction->fresh()->monthly_amount);
    }

    // ── the monthly amount ──────────────────────────────────────────────

    public function test_changing_the_monthly_amount_works_out_the_months_left_again_and_is_logged(): void
    {
        $deduction = $this->made([], $this->pdf());

        $this->post(route('scheduled-deductions.monthly', $this->id($deduction)), ['monthly_amount' => '5000', 'reason' => 'Employee asked to clear it sooner'])->assertSessionHasNoErrors();

        $deduction->refresh();
        $this->assertEquals(5000, $deduction->monthly_amount);
        $this->assertSame(5, $deduction->months, '21,500 at 5,000 is 4 months and a part month');
        $this->assertSame('2027-04', $deduction->end_month->format('Y-m'), 'from the start month, Dec 2026');
        $event = $deduction->events()->where('event', 'instalment_changed')->firstOrFail();
        $this->assertSame('Employee asked to clear it sooner', $event->reason);
        $this->assertStringContainsString('2,000.00', $event->details);
        $this->assertStringContainsString('5,000.00', $event->details);
    }

    public function test_the_new_monthly_amount_and_a_reason_are_both_needed(): void
    {
        $deduction = $this->made([], $this->pdf());

        $this->post(route('scheduled-deductions.monthly', $this->id($deduction)), ['monthly_amount' => '', 'reason' => 'some reason here'])->assertSessionHasErrors('monthly_amount');
        $this->post(route('scheduled-deductions.monthly', $this->id($deduction)), ['monthly_amount' => '0', 'reason' => 'some reason here'])->assertSessionHasErrors('monthly_amount');
        $this->post(route('scheduled-deductions.monthly', $this->id($deduction)), ['monthly_amount' => '1500', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->assertEquals(2000, $deduction->fresh()->monthly_amount);
    }

    // ── what is left to take ────────────────────────────────────────────

    public function test_the_schedule_shows_every_month_still_to_come_with_the_short_last_month(): void
    {
        $deduction = $this->made([], $this->pdf());

        $schedule = app(ScheduledDeductions::class)->schedule($deduction);

        $this->assertSame(11, $schedule['months_left']);
        $this->assertSame('2026-12', $schedule['rows'][0]['month']);
        $this->assertSame(1500.0, $schedule['rows'][10]['amount']);
        $this->assertSame('2027-10', $schedule['end_month']);
    }

    public function test_a_start_month_that_has_gone_by_does_not_ask_for_the_missed_months_back(): void
    {
        $this->actingAsHr();
        $this->save(['start_month' => '2026-09'])->assertSessionHasNoErrors();

        $schedule = app(ScheduledDeductions::class)->schedule(ScheduledDeduction::firstOrFail());

        $this->assertSame('2026-11', $schedule['rows'][0]['month'], 'this month, not September');
    }

    public function test_the_months_after_the_last_one_taken_are_what_is_left(): void
    {
        $deduction = $this->made([], $this->pdf());
        $deduction->repeats()->create(['deduction_date' => '2026-12-01', 'amount' => 2000, 'balance' => 19500]);
        $deduction->update(['balance' => 19500]);
        $this->travelTo(Carbon::parse('2026-12-20 09:00:00'));

        $schedule = app(ScheduledDeductions::class)->schedule($deduction->fresh());

        $this->assertSame('2027-01', $schedule['rows'][0]['month']);
        $this->assertSame(10, $schedule['months_left']);
        $this->assertCount(1, $schedule['taken']);
    }

    public function test_the_preview_gives_the_months_and_the_problems_without_saving_anything(): void
    {
        $this->actingAsHr();

        $good = $this->postJson(route('scheduled-deductions.preview'), ['total_amount' => '21500', 'plan_by' => 'monthly', 'monthly_amount' => '2000', 'start_month' => '2026-12'])->assertOk()->json();
        $this->assertSame(11, $good['months']);
        $this->assertCount(11, $good['rows']);
        $this->assertSame('Oct 2027', $good['end_month']);
        $this->assertSame([], $good['problems']);

        $bad = $this->postJson(route('scheduled-deductions.preview'), ['total_amount' => '5000000', 'plan_by' => 'monthly', 'monthly_amount' => '10', 'start_month' => '2026-12'])->assertOk()->json();
        $this->assertSame([], $bad['rows']);
        $this->assertNotEmpty($bad['problems']);
        $this->assertSame(0, ScheduledDeduction::count());
    }

    // ── the pages ───────────────────────────────────────────────────────

    public function test_the_list_filters_by_status_and_shows_the_balance(): void
    {
        $onHold = $this->made(['description' => 'laptop damaged']);
        $active = $this->made(['total_amount' => '9000', 'monthly_amount' => '3000'], $this->pdf());

        $open = $this->get(route('scheduled-deductions.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<select name="status"', $open, 'the status is a filter box, not a row of tabs');
        $this->assertStringContainsString('Open (2)', $open);
        $this->assertStringContainsString('On hold (1)', $open);
        $this->assertStringContainsString('Active (1)', $open);
        $this->assertStringNotContainsString('nav-pills', $open);
        $this->assertStringContainsString('ALICE WANJIRU', $open);
        $this->assertStringContainsString('laptop damaged', $open);
        $this->assertStringContainsString('9,000.00', $open);

        $activeOnly = $this->get(route('scheduled-deductions.index', ['status' => 'active']))->getContent();
        $this->assertStringContainsString('9,000.00', $activeOnly);
        $this->assertStringNotContainsString('laptop damaged', $activeOnly);

        $this->assertSame(ScheduledDeduction::ON_HOLD, $onHold->fresh()->status);
        $this->assertSame(ScheduledDeduction::ACTIVE, $active->fresh()->status);
    }

    public function test_an_empty_list_shows_a_message_and_no_table(): void
    {
        $this->actingAsHr();

        $page = $this->get(route('scheduled-deductions.index', ['status' => 'on_hold']))->assertOk()->getContent();

        $this->assertStringContainsString('There are no deductions here yet.', $page);
        $this->assertStringNotContainsString('id="sd-table"', $page);
        $this->assertStringContainsString('<option value="on_hold" selected>On hold (0)</option>', $page);
    }

    public function test_the_staff_list_on_the_add_form_can_be_searched(): void
    {
        $this->actingAsHr();

        $page = $this->get(route('scheduled-deductions.create'))->assertOk()->getContent();

        $this->assertStringContainsString("select2({ width: '100%'", $page);
        $this->assertStringContainsString('E-001 - ALICE WANJIRU', $page, 'each choice has the staff number and the name, so either can be typed');
    }

    public function test_the_details_page_shows_the_on_hold_notice_the_schedule_and_the_history(): void
    {
        $deduction = $this->made(['description' => 'school fees']);

        $page = $this->get(route('scheduled-deductions.show', $this->id($deduction)))->assertOk()->getContent();

        $this->assertStringContainsString('Nothing is taken from the pay until the signed consent form is added', $page);
        $this->assertStringContainsString('Dec 2026', $page);
        $this->assertStringContainsString('Oct 2027', $page);
        $this->assertStringContainsString('school fees', $page);
        $this->assertStringContainsString('Hannah HR', $page);
        $this->assertStringContainsString('Add consent form', $page);
        $this->assertStringContainsString('Close early', $page);
    }

    public function test_the_add_form_offers_the_three_ways_to_work_out_the_schedule(): void
    {
        $this->actingAsHr();

        $page = $this->get(route('scheduled-deductions.create'))->assertOk()->getContent();

        $this->assertStringContainsString('name="plan_by"', $page);
        $this->assertStringContainsString('The monthly amount', $page);
        $this->assertStringContainsString('The number of months', $page);
        $this->assertStringContainsString('The month it should finish by', $page);
        $this->assertStringContainsString('Signed consent form (PDF)', $page);
        $this->assertStringContainsString('Salary advance (company loan)', $page);
    }

    // ── who may do what ─────────────────────────────────────────────────

    public function test_without_the_read_permission_nothing_can_be_opened(): void
    {
        $deduction = $this->made([], $this->pdf());
        $this->actingWith(['create_scheduled_deductions']);

        $this->get(route('scheduled-deductions.index'))->assertForbidden();
        $this->get(route('scheduled-deductions.show', $this->id($deduction)))->assertForbidden();
        $this->get(route('scheduled-deductions.consent.download', $this->id($deduction)))->assertForbidden();
    }

    public function test_without_the_create_permission_a_deduction_cannot_be_added(): void
    {
        $this->actingWith(['read_scheduled_deductions', 'update_scheduled_deductions']);

        $this->get(route('scheduled-deductions.create'))->assertForbidden();
        $this->save()->assertForbidden();
        $this->postJson(route('scheduled-deductions.preview'), ['total_amount' => '100'])->assertForbidden();
        $this->assertSame(0, ScheduledDeduction::count());
    }

    public function test_closing_early_is_its_own_permission(): void
    {
        $deduction = $this->made([], $this->pdf());
        $this->actingWith(['read_scheduled_deductions', 'update_scheduled_deductions']);

        $this->post(route('scheduled-deductions.close', $this->id($deduction)), ['reason' => 'closing it early'])->assertForbidden();
        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => 'pausing for leave'])->assertSessionHasNoErrors();
        $this->assertSame(ScheduledDeduction::PAUSED, $deduction->fresh()->status);

        $page = $this->get(route('scheduled-deductions.show', $this->id($deduction)))->assertOk()->getContent();
        $this->assertStringNotContainsString('Close early', $page, 'the button is not shown to someone who cannot use it');
        $this->assertStringContainsString('Resume', $page);
    }

    public function test_changing_things_needs_the_update_permission(): void
    {
        $deduction = $this->made();
        $this->actingWith(['read_scheduled_deductions', 'create_scheduled_deductions', 'close_scheduled_deductions']);

        $this->post(route('scheduled-deductions.consent.store', $this->id($deduction)), ['consent' => $this->pdf()])->assertForbidden();
        $this->post(route('scheduled-deductions.monthly', $this->id($deduction)), ['monthly_amount' => '1000', 'reason' => 'reason here'])->assertForbidden();
        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => 'reason here'])->assertForbidden();
        $this->post(route('scheduled-deductions.resume', $this->id($deduction)))->assertForbidden();
        $this->assertSame(ScheduledDeduction::ON_HOLD, $deduction->fresh()->status);
    }

    public function test_the_types_are_for_those_who_manage_them(): void
    {
        $this->actingWith(['read_scheduled_deductions', 'create_scheduled_deductions']);

        $this->post(route('scheduled-deductions.types.store'), ['name' => 'Staff mortgage'])->assertForbidden();
        $this->post(route('scheduled-deductions.types.toggle', 4))->assertForbidden();
        $this->assertTrue(DeductionType::find(4)->is_active);
        $this->assertNull(DeductionType::where('name', 'Staff mortgage')->first());
        $this->assertStringNotContainsString('Deduction types', $this->get(route('scheduled-deductions.index'))->getContent());
    }

    public function test_every_change_is_written_to_the_history_with_who_did_it(): void
    {
        $deduction = $this->made([], $this->pdf());
        $this->post(route('scheduled-deductions.pause', $this->id($deduction)), ['reason' => 'On unpaid leave']);
        $this->post(route('scheduled-deductions.resume', $this->id($deduction)));
        $this->post(route('scheduled-deductions.monthly', $this->id($deduction)), ['monthly_amount' => '3000', 'reason' => 'Clearing it sooner']);

        $events = ScheduledDeductionEvent::where('deduction_id', $deduction->id)->orderBy('id')->get();

        $this->assertSame(['created', 'consent_added', 'paused', 'resumed', 'instalment_changed'], $events->pluck('event')->all());
        $this->assertSame([self::ME], $events->pluck('user_id')->unique()->map(fn ($id) => (int) $id)->all());
    }
}

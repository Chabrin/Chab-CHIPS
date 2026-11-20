<?php

namespace Tests\Feature;

use App\Models\HelbDeduction;
use App\Models\HelbDeductionEvent;
use App\Models\User;
use App\Services\HelbLoans;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * HELB Loans (UAT A6): HELB is deducted by law, so there is no consent form. What a loan has instead is a reason for every pause, change
 * and closing, HELB's clearance to close it, a history, the months left, and the staff member's HELB status from onboarding.
 */
class HelbLoansTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const STAFF = 1;
    private const ME = 7;

    private const EVERYTHING = ['read_helb_loans', 'create_helb_loans', 'update_helb_loans', 'delete_helb_loans'];

    protected function setUp(): void
    {
        parent::setUp();
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
            $t->boolean('is_helb_beneficiary')->nullable();
            $t->text('helb_certificate_exception_reason')->nullable();
            $t->unsignedBigInteger('helb_certificate_exception_by')->nullable();
            $t->timestamp('helb_certificate_exception_at')->nullable();
        });
        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); });
        Schema::create('document_types', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('document_type_id');
            $t->string('file_path')->nullable();
        });
        // The HELB tables as the old system left them, then what this change adds
        Schema::create('sys_helb_deductions', function (Blueprint $t) {
            $t->id();
            $t->dateTime('date_time')->nullable();
            $t->integer('created_by_id')->nullable();
            $t->integer('staff_id');
            $t->decimal('loan_balance', 15, 2)->default(0);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->date('start_deduction_date')->nullable();
            $t->integer('current_status_id')->nullable();
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
        });
        Schema::create('sys_helb_deductions_250_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->dateTime('deduction_date')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('loan_balance', 15, 2)->default(0);
            $t->integer('current_status_id')->nullable();
            $t->integer('period_id')->nullable();
        });
        (require database_path('migrations/2026_11_20_200000_add_pause_clearance_and_history_to_helb_deductions.php'))->up();

        DB::table('sys_staff')->insert([
            ['id' => self::STAFF, 'staff_number' => 'E-001', 'full_names' => 'ALICE WANJIRU', 'is_helb_beneficiary' => true],
            ['id' => 2, 'staff_number' => 'E-002', 'full_names' => 'BOB OTIENO', 'is_helb_beneficiary' => null],
        ]);
        DB::table('josya_users')->insert(['id' => self::ME, 'name' => 'Hannah HR']);
        DB::table('document_types')->insert(['id' => 1, 'name' => HelbLoans::CERTIFICATE]);
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

    private function loan(array $over = []): HelbDeduction
    {
        return HelbDeduction::create($over + [
            'staff_id' => self::STAFF, 'loan_balance' => 5000, 'monthly_deduction' => 1500, 'start_deduction_date' => '2026-10-01',
            'cleared' => 0, 'suspended' => 0, 'date_time' => now(), 'created_by_id' => self::ME,
        ]);
    }

    private function id(HelbDeduction $loan): string
    {
        return Crypt::encrypt($loan->id);
    }

    private function pdf(string $name = 'helb-clearance.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    // ── the list ────────────────────────────────────────────────────────

    public function test_the_list_gives_the_status_and_the_last_month_it_will_be_taken(): void
    {
        $this->actingAsHr();
        $this->loan(['loan_balance' => 5000, 'monthly_deduction' => 1500]);
        $this->loan(['staff_id' => 2, 'suspended' => 1]);
        $this->loan(['staff_id' => 2, 'cleared' => 1]);

        $rows = collect($this->getJson('/ajax/deductions/helb/list')->assertOk()->json('deductions'))->keyBy('id');

        $this->assertSame('Active', $rows[1]['status']);
        $this->assertSame('Paused', $rows[2]['status']);
        $this->assertSame('Cleared', $rows[3]['status']);
        $this->assertSame(4, $rows[1]['months_left'], 'Nov, Dec, Jan and Feb: 1,500 three times and 500');
        $this->assertSame('Feb 2027', $rows[1]['end_month']);
        $this->assertNull($rows[3]['end_month'], 'nothing is left to take once it is closed');
    }

    public function test_the_list_page_has_the_filter_and_the_ends_column(): void
    {
        $this->actingAsHr();

        $page = $this->get(route('deductions.helb'))->assertOk()->getContent();

        $this->assertStringContainsString('id="helbStatusFilter"', $page);
        $this->assertStringContainsString('Open (active and paused)', $page);
        $this->assertStringContainsString('<th>Ends</th>', $page);
        $this->assertStringNotContainsString('id="cleared"', $page, 'pausing and closing are no longer tick boxes on the edit form');
        $this->assertStringNotContainsString('id="suspended"', $page);
        $this->assertStringContainsString('id="change_reason"', $page);
        $this->assertStringContainsString('id="helbStaffStatus"', $page);
    }

    // ── adding and changing ─────────────────────────────────────────────

    public function test_adding_a_loan_writes_the_first_line_of_its_history(): void
    {
        $this->actingAsHr();

        $this->postJson('/ajax/deductions/helb', ['staff_id' => self::STAFF, 'loan_balance' => 5000, 'monthly_deduction' => 1500, 'start_deduction_date' => '2026-10-01'])
            ->assertOk()->assertJson(['success' => true]);

        $event = HelbDeductionEvent::firstOrFail();
        $this->assertSame('created', $event->event);
        $this->assertSame(self::ME, (int) $event->user_id);
        $this->assertStringContainsString('5,000.00 owed', $event->details);
        $this->assertStringContainsString('1,500.00 a month', $event->details);
    }

    public function test_changing_the_monthly_amount_needs_a_reason_and_is_written_down(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();

        $this->postJson('/ajax/deductions/helb/' . $loan->id, ['loan_balance' => 5000, 'monthly_deduction' => 2000, 'start_deduction_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertEquals(1500, $loan->fresh()->monthly_deduction);

        $this->postJson('/ajax/deductions/helb/' . $loan->id, ['loan_balance' => 5000, 'monthly_deduction' => 2000, 'start_deduction_date' => '2026-10-01', 'reason' => 'HELB raised the rate on the Employer Portal'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(2000, $loan->fresh()->monthly_deduction);
        $event = $loan->events()->where('event', 'changed')->firstOrFail();
        $this->assertSame('HELB raised the rate on the Employer Portal', $event->reason);
        $this->assertStringContainsString('Monthly amount KES 1,500.00 to KES 2,000.00', $event->details);
        $this->assertStringNotContainsString('Balance', $event->details, 'only what changed is listed');
    }

    public function test_saving_without_changing_anything_needs_no_reason_and_adds_no_history(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();

        $this->postJson('/ajax/deductions/helb/' . $loan->id, ['loan_balance' => 5000, 'monthly_deduction' => 1500, 'start_deduction_date' => '2026-10-01'])->assertOk();

        $this->assertSame(0, $loan->events()->count());
    }

    public function test_the_edit_form_can_no_longer_pause_or_close_a_loan(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();

        $this->postJson('/ajax/deductions/helb/' . $loan->id, ['loan_balance' => 5000, 'monthly_deduction' => 1500, 'start_deduction_date' => '2026-10-01', 'cleared' => 1, 'suspended' => 1])->assertOk();

        $this->assertSame(0, (int) $loan->fresh()->cleared);
        $this->assertSame(0, (int) $loan->fresh()->suspended);
    }

    public function test_a_closed_loan_cannot_be_changed_until_it_is_reopened(): void
    {
        $this->actingAsHr();
        $loan = $this->loan(['cleared' => 1]);

        $this->postJson('/ajax/deductions/helb/' . $loan->id, ['loan_balance' => 5000, 'monthly_deduction' => 2500, 'start_deduction_date' => '2026-10-01', 'reason' => 'a good enough reason'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertEquals(1500, $loan->fresh()->monthly_deduction);
    }

    // ── pause and resume ────────────────────────────────────────────────

    public function test_pausing_needs_a_reason_and_stops_the_deduction(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();

        $this->post(route('deductions.helb.pause', $this->id($loan)), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('deductions.helb.pause', $this->id($loan)), ['reason' => 'abc'])->assertSessionHasErrors('reason');
        $this->assertSame(0, (int) $loan->fresh()->suspended);

        $this->post(route('deductions.helb.pause', $this->id($loan)), ['reason' => 'On unpaid leave until March'])->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(1, (int) $loan->suspended);
        $this->assertSame('On unpaid leave until March', $loan->suspended_reason);
        $this->assertNotNull($loan->suspended_at);
        $this->assertSame('On unpaid leave until March', $loan->events()->where('event', 'paused')->firstOrFail()->reason);
    }

    public function test_a_paused_loan_cannot_be_paused_again_and_resuming_clears_the_pause(): void
    {
        $this->actingAsHr();
        $loan = $this->loan(['suspended' => 1, 'suspended_reason' => 'Leave']);

        $this->post(route('deductions.helb.pause', $this->id($loan)), ['reason' => 'a good enough reason'])->assertSessionHasErrors('reason');

        $this->post(route('deductions.helb.resume', $this->id($loan)))->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(0, (int) $loan->suspended);
        $this->assertNull($loan->suspended_reason);
        $this->assertContains('resumed', $loan->events()->pluck('event')->all());
        $this->post(route('deductions.helb.resume', $this->id($loan)))->assertSessionHasErrors('reason');
    }

    // ── closing with HELB's clearance ───────────────────────────────────

    public function test_a_loan_is_closed_with_helbs_clearance_and_the_file_is_kept(): void
    {
        $this->actingAsHr();
        $loan = $this->loan(['loan_balance' => 1200]);

        $this->post(route('deductions.helb.clear', $this->id($loan)), ['note' => 'Clearance letter of 18 Nov'])->assertSessionHasErrors('clearance');
        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => UploadedFile::fake()->image('letter.png')])->assertSessionHasErrors('clearance');
        $this->assertSame(0, (int) $loan->fresh()->cleared);

        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf('helb-paid-in-full.pdf'), 'note' => 'Clearance letter of 18 Nov'])->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(1, (int) $loan->cleared);
        $this->assertSame('helb-paid-in-full.pdf', $loan->clearance_name);
        Storage::disk('local')->assertExists($loan->clearance_path);
        $this->assertSame(self::ME, (int) $loan->cleared_by);
        $this->assertNotNull($loan->cleared_at);
        $event = $loan->events()->where('event', 'cleared')->firstOrFail();
        $this->assertSame('Clearance letter of 18 Nov', $event->reason);
        $this->assertStringContainsString('1,200.00 was still shown as owed', $event->details, 'a difference with HELB can be seen later');
        $this->assertEquals(1200, $loan->loan_balance, 'what the system showed is not rewritten');
    }

    public function test_closing_works_on_a_paused_loan_and_clears_the_pause(): void
    {
        $this->actingAsHr();
        $loan = $this->loan(['suspended' => 1, 'suspended_reason' => 'Leave']);

        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf()])->assertSessionHasNoErrors();

        $this->assertSame(0, (int) $loan->fresh()->suspended);
        $this->assertSame('Cleared', $loan->fresh()->status_label);
    }

    public function test_a_closed_loan_cannot_be_paused_or_closed_again(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();
        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf()]);

        $this->post(route('deductions.helb.pause', $this->id($loan)), ['reason' => 'a good enough reason'])->assertSessionHasErrors('reason');
        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf('second.pdf')])->assertSessionHasErrors('reason');
        $this->assertSame(1, $loan->events()->where('event', 'cleared')->count());
    }

    public function test_the_clearance_can_be_opened_and_a_loan_without_one_is_not_found(): void
    {
        $this->actingAsHr();
        $closed = $this->loan();
        $this->post(route('deductions.helb.clear', $this->id($closed)), ['clearance' => $this->pdf()]);
        $oldClosed = $this->loan(['cleared' => 1]); // closed before clearances were kept

        $this->get(route('deductions.helb.clearance', $this->id($closed)))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('deductions.helb.clearance', $this->id($oldClosed)))->assertNotFound();
        $this->get(route('deductions.helb.clearance', 'not-a-real-id'))->assertNotFound();
    }

    public function test_a_closed_loan_is_reopened_with_a_reason_and_keeps_its_clearance_on_file(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();
        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf()]);

        $this->post(route('deductions.helb.reopen', $this->id($loan)), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('deductions.helb.reopen', $this->id($loan)), ['reason' => 'HELB says KES 800 is still unpaid'])->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(0, (int) $loan->cleared);
        $this->assertNull($loan->cleared_at);
        $this->assertNotNull($loan->clearance_path, 'the clearance that was added stays on file');
        $this->assertSame('HELB says KES 800 is still unpaid', $loan->events()->where('event', 'reopened')->firstOrFail()->reason);
        $this->post(route('deductions.helb.reopen', $this->id($loan)), ['reason' => 'again, for no reason'])->assertSessionHasErrors('reason');
    }

    // ── the staff member's HELB status from onboarding ──────────────────

    public function test_the_status_says_whether_the_profile_has_a_beneficiary_and_a_certificate(): void
    {
        $this->actingAsHr();
        DB::table('employee_documents')->insert(['employee_id' => self::STAFF, 'document_type_id' => 1, 'file_path' => 'x.pdf']);

        $alice = $this->getJson('/ajax/deductions/helb/staff/' . self::STAFF . '/status')->assertOk()->json();
        $this->assertTrue($alice['beneficiary']);
        $this->assertSame('on_file', $alice['certificate']);
        $this->assertFalse($alice['running_loan']);

        $bob = $this->getJson('/ajax/deductions/helb/staff/2/status')->assertOk()->json();
        $this->assertNull($bob['beneficiary'], 'not answered');
        $this->assertSame('missing', $bob['certificate']);
    }

    public function test_a_waived_certificate_shows_the_reason_and_a_running_loan_is_noticed(): void
    {
        $this->actingAsHr();
        DB::table('sys_staff')->where('id', 2)->update(['is_helb_beneficiary' => false, 'helb_certificate_exception_reason' => 'Certificate lost, HELB confirmed by phone', 'helb_certificate_exception_at' => now()]);
        $this->loan(['staff_id' => 2]);

        $bob = $this->getJson('/ajax/deductions/helb/staff/2/status')->assertOk()->json();

        $this->assertFalse($bob['beneficiary']);
        $this->assertSame('exception', $bob['certificate']);
        $this->assertSame('Certificate lost, HELB confirmed by phone', $bob['exception_reason']);
        $this->assertTrue($bob['running_loan']);
    }

    public function test_the_status_is_only_for_those_who_can_add_a_loan(): void
    {
        $this->actingWith(['read_helb_loans']);

        $this->getJson('/ajax/deductions/helb/staff/' . self::STAFF . '/status')->assertForbidden();
    }

    // ── the schedule ────────────────────────────────────────────────────

    public function test_the_months_left_come_from_what_is_owed_and_the_monthly_amount(): void
    {
        $loan = $this->loan(['loan_balance' => 4000, 'monthly_deduction' => 1500]);

        $schedule = app(HelbLoans::class)->schedule($loan);

        $this->assertSame(3, $schedule['months_left']);
        $this->assertSame(['2026-11', '2026-12', '2027-01'], array_column($schedule['rows'], 'month'), 'this month on, not the months that have gone by');
        $this->assertSame([1500.0, 1500.0, 1000.0], array_column($schedule['rows'], 'amount'));
        $this->assertSame('2027-01', $schedule['end_month']);
    }

    public function test_the_schedule_starts_after_the_last_instalment_taken_and_at_a_later_start_date(): void
    {
        $loan = $this->loan(['loan_balance' => 3000, 'monthly_deduction' => 1500]);
        DB::table('sys_helb_deductions_250_repeat')->insert(['parent_id' => $loan->id, 'deduction_date' => '2026-12-01', 'amount' => 1500, 'loan_balance' => 3000]);
        $this->assertSame('2027-01', app(HelbLoans::class)->schedule($loan)['rows'][0]['month']);

        $later = $this->loan(['start_deduction_date' => '2027-03-01']);
        $this->assertSame('2027-03', app(HelbLoans::class)->schedule($later)['rows'][0]['month']);
    }

    public function test_a_closed_loan_or_one_with_nothing_owed_has_no_schedule(): void
    {
        $this->assertSame(0, app(HelbLoans::class)->schedule($this->loan(['cleared' => 1]))['months_left']);
        $this->assertSame(0, app(HelbLoans::class)->schedule($this->loan(['loan_balance' => 0]))['months_left']);
        $this->assertSame(0, app(HelbLoans::class)->schedule($this->loan(['monthly_deduction' => 0]))['months_left']);
    }

    // ── the details page ────────────────────────────────────────────────

    public function test_the_details_page_shows_the_schedule_the_staff_status_and_the_buttons(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();
        DB::table('sys_staff')->where('id', self::STAFF)->update(['is_helb_beneficiary' => true]);

        $page = $this->get(route('deductions.helb.show', $this->id($loan)))->assertOk()->getContent();

        $this->assertStringContainsString('Months left', $page);
        $this->assertStringContainsString('Feb 2027', $page);
        $this->assertStringContainsString('HELB beneficiary:', $page);
        $this->assertStringContainsString('Not on file', $page, 'the certificate');
        $this->assertStringContainsString('Pause', $page);
        $this->assertStringContainsString('Close with HELB clearance', $page);
        $this->assertStringNotContainsString('Reopen', $page);
        $this->assertStringContainsString('id="changes-tab"', $page);
    }

    public function test_a_paused_loan_shows_why_and_offers_resume(): void
    {
        $this->actingAsHr();
        $loan = $this->loan(['suspended' => 1, 'suspended_reason' => 'On unpaid leave', 'suspended_at' => now()]);

        $page = $this->get(route('deductions.helb.show', $this->id($loan)))->assertOk()->getContent();

        $this->assertStringContainsString('Paused on 20 Nov 2026', $page);
        $this->assertStringContainsString('On unpaid leave', $page);
        $this->assertStringContainsString('Resume', $page);
        $this->assertStringNotContainsString('data-bs-target="#pauseModal"', $page);
    }

    public function test_an_old_pause_with_no_reason_says_so_rather_than_showing_nothing(): void
    {
        $this->actingAsHr();
        $loan = $this->loan(['suspended' => 1]);

        $page = $this->get(route('deductions.helb.show', $this->id($loan)))->assertOk()->getContent();

        $this->assertStringContainsString('not recorded (paused before reasons were kept)', $page);
    }

    public function test_a_closed_loan_shows_the_clearance_and_offers_reopen_only(): void
    {
        $this->actingAsHr();
        $loan = $this->loan();
        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf('helb-paid-in-full.pdf')]);

        $page = $this->get(route('deductions.helb.show', $this->id($loan)))->assertOk()->getContent();

        $this->assertStringContainsString('View HELB', $page);
        $this->assertStringContainsString('helb-paid-in-full.pdf', $page);
        $this->assertStringContainsString('Reopen', $page);
        $this->assertStringNotContainsString('Close with HELB clearance', $page);
        $this->assertStringContainsString('Closed with HELB clearance', $page, 'the history');
        $this->assertStringContainsString('Hannah HR', $page);
    }

    public function test_the_buttons_are_not_shown_without_the_update_permission(): void
    {
        $loan = $this->loan();
        $this->actingWith(['read_helb_loans']);

        $page = $this->get(route('deductions.helb.show', $this->id($loan)))->assertOk()->getContent();

        $this->assertStringNotContainsString('Close with HELB clearance', $page);
        $this->assertStringNotContainsString('data-bs-target="#pauseModal"', $page);
    }

    // ── who may do what ─────────────────────────────────────────────────

    public function test_pausing_closing_and_reopening_need_the_update_permission(): void
    {
        $loan = $this->loan();
        $this->actingWith(['read_helb_loans', 'create_helb_loans']);

        $this->post(route('deductions.helb.pause', $this->id($loan)), ['reason' => 'a good enough reason'])->assertForbidden();
        $this->post(route('deductions.helb.resume', $this->id($loan)))->assertForbidden();
        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf()])->assertForbidden();
        $this->post(route('deductions.helb.reopen', $this->id($loan)), ['reason' => 'a good enough reason'])->assertForbidden();
        $this->assertSame(0, (int) $loan->fresh()->suspended);
        $this->assertSame(0, (int) $loan->fresh()->cleared);
    }

    public function test_the_clearance_needs_the_read_permission(): void
    {
        $loan = $this->loan();
        $this->actingWith(['update_helb_loans']);
        $this->post(route('deductions.helb.clear', $this->id($loan)), ['clearance' => $this->pdf()])->assertSessionHasNoErrors();

        $this->get(route('deductions.helb.clearance', $this->id($loan)))->assertForbidden();
    }
}

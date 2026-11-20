<?php

namespace Tests\Feature;

use App\Models\HelbDeduction;
use App\Models\PayrollCalendar;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSummary;
use App\Models\User;
use App\Services\PayrollMonthOpener;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPayrollRunTables;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * After approval the accountant confirms the bank payment. That locks the run and opens the next payroll month, and the
 * people who work on payroll are told in the app.
 */
class PayrollPaymentConfirmationTest extends TestCase
{
    use BuildsPayrollRunTables;
    use RendersPagesWithPlainLayout;

    private const HR_ID = 7;              // processed and submitted the run
    private const ACCOUNTANT_ID = 8;      // confirms the payment
    private const OTHER_HR_ID = 9;        // another person who can process payroll
    private const OTHER_ACCOUNTANT_ID = 10;
    private const OUTSIDER_ID = 55;       // no payroll role at all
    private const PERIOD_ID = 216;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->usePlainLayout();
        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        $this->buildPayrollRunTables();

        DB::table('cb_roles')->insert([['id' => 11, 'name' => 'hr_manager'], ['id' => 44, 'name' => 'accountant']]);
        DB::table('cb_permissions')->insert([['id' => 1, 'name' => 'create_payroll_processing'], ['id' => 2, 'name' => 'approve_payroll_processing']]);
        DB::table('cb_permission_role')->insert([['permission_id' => 1, 'role_id' => 11], ['permission_id' => 2, 'role_id' => 44]]);
        DB::table('cb_role_user')->insert([
            ['role_id' => 11, 'user_id' => self::HR_ID], ['role_id' => 11, 'user_id' => self::OTHER_HR_ID],
            ['role_id' => 44, 'user_id' => self::ACCOUNTANT_ID], ['role_id' => 44, 'user_id' => self::OTHER_ACCOUNTANT_ID],
        ]);
        DB::table('josya_users')->insert([
            ['id' => self::HR_ID, 'name' => 'Hannah HR'], ['id' => self::ACCOUNTANT_ID, 'name' => 'Amina Accountant'],
            ['id' => self::OTHER_HR_ID, 'name' => 'Otieno HR'], ['id' => self::OTHER_ACCOUNTANT_ID, 'name' => 'Zuri Accountant'],
        ]);

        $calendar = PayrollCalendar::create(['payroll_month_date' => '202610', 'current_status_id' => 5, 'date_time' => now()]);
        DB::table('sys_hr_payroll_periods')->insert(['id' => self::PERIOD_ID, 'calendar_id' => $calendar->id, 'period_number' => 1, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'current_status_id' => 5]);
        DB::table('sys_set_banks')->insert(['id' => 1, 'bank_name' => 'Test Bank']);
        DB::table('sys_staff')->insert([
            ['id' => 1, 'staff_number' => 'S1', 'full_names' => 'Paid Person', 'bank_name' => 1, 'account_number' => '0012345'],
            ['id' => 2, 'staff_number' => 'S2', 'full_names' => 'No Bank Person', 'bank_name' => null, 'account_number' => null],
        ]);
        foreach ([1 => 50000, 2 => 40000] as $staffId => $netPay) {
            PayrollSummary::create(['staff_id' => $staffId, 'period' => '2026-10-01', 'period_id' => self::PERIOD_ID, 'net_pay' => $netPay, 'current_status_id' => 5]);
        }
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actingWith(int $userId, array $permissions, string $name = 'Someone'): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = $userId;
        $user->name = $name;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function actingAsHr(): void
    {
        $this->actingWith(self::HR_ID, ['read_payroll_processing', 'create_payroll_processing'], 'Hannah HR');
    }

    private function actingAsAccountant(): void
    {
        $this->actingWith(self::ACCOUNTANT_ID, ['read_payroll_processing', 'approve_payroll_processing'], 'Amina Accountant');
    }

    private function run_(string $status, int $processedBy = self::HR_ID): PayrollRun
    {
        return PayrollRun::updateOrCreate(['period_id' => self::PERIOD_ID], [
            'status' => $status,
            'processed_by' => $processedBy,
            'submitted_by' => $processedBy,
            'approved_by' => in_array($status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID], true) ? self::OTHER_ACCOUNTANT_ID : null,
        ]);
    }

    private function post_(string $action, array $data = [])
    {
        return $this->postJson('/ajax/payroll/periods/' . self::PERIOD_ID . '/' . $action, $data);
    }

    private function pay(array $overrides = [])
    {
        return $this->post_('pay', $overrides + [
            'payment_date' => now()->toDateString(),
            'payment_reference' => 'EQB-20261117-001',
            'payment_note' => 'Paid from the main account',
            'confirmed' => 1,
            'net_total' => 90000,
            'employees' => 2,
        ]);
    }

    private function notified(int $userId)
    {
        return DB::table('notifications')->where('notifiable_id', $userId)->where('type', 'PayrollRun')->get();
    }

    // ── confirming payment ───────────────────────────────────────────────

    public function test_the_accountant_marks_an_approved_run_as_paid_and_it_keeps_who_when_and_the_reference(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->pay(['payment_date' => '2026-11-16'])->assertOk()->assertJson(['success' => true]);

        $run = PayrollRun::first();
        $this->assertSame(PayrollRun::STATUS_PAID, $run->status);
        $this->assertSame(self::ACCOUNTANT_ID, (int) $run->paid_by);
        $this->assertNotNull($run->paid_at);
        $this->assertSame('2026-11-16', $run->payment_date->toDateString());
        $this->assertSame('EQB-20261117-001', $run->payment_reference);
        $this->assertSame('Paid from the main account', $run->payment_note);

        $entry = $run->logs->firstWhere('action', 'paid');
        $this->assertSame(self::ACCOUNTANT_ID, (int) $entry->user_id);
        $this->assertStringContainsString('EQB-20261117-001', $entry->comment);
    }

    public function test_paying_opens_the_next_month_with_a_whole_month_period(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $response = $this->pay()->assertOk();

        $this->assertStringContainsString('November 2026 payroll is now open', $response->json('message'));
        $this->assertNotNull(PayrollCalendar::where('payroll_month_date', '202611')->first());
        $period = PayrollPeriod::whereDate('start_date', '2026-11-01')->firstOrFail();
        $this->assertSame('2026-11-30', $period->end_date->toDateString());
        $this->assertSame(self::ACCOUNTANT_ID, (int) $period->created_by);
        $this->assertSame('month_opened', PayrollRun::first()->logs->first()->action);
    }

    public function test_approving_alone_opens_nothing(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        PayrollSummary::where('period_id', self::PERIOD_ID)->update(['current_status_id' => 1]);
        $this->actingAsAccountant();

        $this->post_('approve')->assertOk();

        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
        $this->assertSame(1, PayrollCalendar::count());
        $this->assertSame(1, PayrollPeriod::count());
    }

    public function test_everyone_working_on_payroll_is_told_in_the_app_but_not_the_person_who_confirmed(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->pay()->assertOk();

        foreach ([self::HR_ID, self::OTHER_HR_ID, self::OTHER_ACCOUNTANT_ID] as $userId) {
            $notice = $this->notified($userId);
            $this->assertCount(1, $notice, "user {$userId} should get one notice");
            $this->assertSame('New payroll month open', $notice[0]->title);
            $this->assertStringContainsString('October 2026 payroll was marked as paid by Amina Accountant', $notice[0]->message);
            $this->assertStringContainsString('November 2026 payroll is now open (01 Nov 2026 to 30 Nov 2026)', $notice[0]->message);
            $this->assertStringEndsWith('/payroll/processing', $notice[0]->action_url);
        }
        $this->assertCount(0, $this->notified(self::ACCOUNTANT_ID));
        $this->assertCount(0, $this->notified(self::OUTSIDER_ID));
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_when_the_next_month_was_already_open_people_are_told_about_the_payment_only(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $november = PayrollCalendar::create(['payroll_month_date' => '202611', 'current_status_id' => 5, 'date_time' => now()]);
        PayrollPeriod::create(['calendar_id' => $november->id, 'period_number' => 1, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'current_status_id' => 5]);
        $this->actingAsAccountant();

        $response = $this->pay()->assertOk();

        $this->assertSame('October 2026 payroll marked as paid.', $response->json('message'));
        $this->assertSame(2, PayrollCalendar::count());
        $this->assertSame(2, PayrollPeriod::count());
        $this->assertSame('Payroll marked as paid', $this->notified(self::HR_ID)[0]->title);
    }

    public function test_a_next_month_added_by_hand_without_a_period_gets_its_dates_when_the_run_is_paid(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $november = PayrollCalendar::create(['payroll_month_date' => '202611', 'current_status_id' => 5, 'date_time' => now()]);
        $this->actingAsAccountant();

        $response = $this->pay()->assertOk();

        $this->assertStringContainsString('November 2026 payroll is now open', $response->json('message'));
        $this->assertSame(2, PayrollCalendar::count());
        $period = PayrollPeriod::where('calendar_id', $november->id)->firstOrFail();
        $this->assertSame('2026-11-01', $period->start_date->toDateString());
        $this->assertSame('2026-11-30', $period->end_date->toDateString());
        $this->assertSame('month_opened', PayrollRun::first()->logs->first()->action);
    }

    public function test_a_paid_period_with_no_calendar_month_is_still_paid_and_hr_is_told_to_add_the_next_month(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        PayrollPeriod::whereKey(self::PERIOD_ID)->update(['calendar_id' => null]);
        $this->actingAsAccountant();

        $response = $this->pay()->assertOk();

        $this->assertSame(PayrollRun::STATUS_PAID, PayrollRun::first()->status);
        $this->assertStringContainsString('Payroll Calendar', $response->json('message'));
        $this->assertSame('month_not_opened', PayrollRun::first()->logs->first()->action);
        $this->assertStringContainsString('could not be opened automatically', $this->notified(self::HR_ID)[0]->message);
    }

    public function test_a_failure_opening_the_next_month_does_not_undo_the_payment(): void
    {
        $this->mock(PayrollMonthOpener::class, fn ($mock) => $mock->shouldReceive('openAfter')->andThrow(new \RuntimeException('boom')));
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $response = $this->pay()->assertOk();

        $this->assertSame(PayrollRun::STATUS_PAID, PayrollRun::first()->status);
        $this->assertStringContainsString('Payroll Calendar', $response->json('message'));
        $this->assertSame('month_not_opened', PayrollRun::first()->logs->first()->action);
        $this->assertStringContainsString('could not be opened automatically', $this->notified(self::HR_ID)[0]->message);
        $this->assertSame(1, PayrollCalendar::count());
    }

    // ── what must be filled in ───────────────────────────────────────────

    public function test_the_bank_reference_and_the_paid_date_are_required(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->pay(['payment_reference' => ''])->assertStatus(422)->assertJsonValidationErrors('payment_reference');
        $this->pay(['payment_date' => ''])->assertStatus(422)->assertJsonValidationErrors('payment_date');

        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
    }

    public function test_the_paid_date_cannot_be_in_the_future(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->pay(['payment_date' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('payment_date');
    }

    public function test_the_confirmation_box_has_to_be_ticked(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->pay(['confirmed' => 0])->assertStatus(422)->assertJsonValidationErrors('confirmed');

        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
    }

    public function test_a_page_that_is_out_of_date_is_refused(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        // The run was reversed and processed again with other figures after this page was opened
        $this->pay(['net_total' => 85000])->assertStatus(422)->assertJsonPath('error', fn ($error) => str_contains($error, 'changed since you opened the page'));
        $this->pay(['employees' => 3])->assertStatus(422);

        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
        $this->assertSame(1, PayrollCalendar::count());
    }

    // ── who and when ─────────────────────────────────────────────────────

    public function test_only_an_approved_run_can_be_marked_paid(): void
    {
        $this->actingAsAccountant();

        foreach ([PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_WITH_ACCOUNTANT, PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_REVERSED] as $status) {
            $this->run_($status);
            $this->pay()->assertStatus(422);
            $this->assertSame($status, PayrollRun::first()->status);
        }
        $this->assertSame(1, PayrollCalendar::count());
    }

    public function test_a_run_cannot_be_marked_paid_twice(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->pay()->assertOk();
        $this->pay()->assertStatus(422)->assertJsonPath('error', 'This payroll has already been marked as paid.');

        $this->assertSame(2, PayrollCalendar::count());
        $this->assertSame(2, PayrollPeriod::count());
        $this->assertCount(1, $this->notified(self::HR_ID));
    }

    public function test_hr_cannot_mark_a_run_as_paid(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsHr();
        $this->withoutExceptionHandling();

        try {
            $this->pay();
            $this->fail('HR was able to mark a payroll run as paid.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
    }

    public function test_the_person_who_processed_the_run_cannot_confirm_its_payment(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED, self::ACCOUNTANT_ID);
        $this->actingAsAccountant();

        $this->pay()->assertStatus(422);

        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
    }

    // ── nothing is undone after payment ──────────────────────────────────

    public function test_a_paid_run_cannot_be_reversed_by_anyone(): void
    {
        $this->run_(PayrollRun::STATUS_PAID);
        $this->actingAsAccountant();
        $this->post_('reverse', ['comment' => 'Wrong amount'])->assertStatus(422)->assertJsonPath('error', fn ($error) => str_contains($error, 'marked as paid'));

        $this->actingAsHr();
        $this->post_('reverse', ['comment' => 'Discard it'])->assertStatus(422);

        $this->assertSame(PayrollRun::STATUS_PAID, PayrollRun::first()->status);
        $this->assertSame(2, PayrollSummary::count());
    }

    public function test_the_eft_file_can_still_be_downloaded_for_a_paid_run(): void
    {
        $this->run_(PayrollRun::STATUS_PAID);
        $this->actingAsAccountant();

        $this->get('/payroll/processing/' . self::PERIOD_ID . '/eft-test')->assertOk();
    }

    // ── rolling back before payment ──────────────────────────────────────

    public function test_an_approved_run_can_still_be_reversed_until_it_is_paid_and_nothing_else_is_left_behind(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->post_('reverse', ['comment' => 'Found an error'])->assertOk();

        $this->assertSame(PayrollRun::STATUS_REVERSED, PayrollRun::first()->status);
        $this->assertSame(0, PayrollSummary::count());
        $this->assertSame(1, PayrollCalendar::count());
        $this->assertSame(1, PayrollPeriod::count());
    }

    public function test_reversing_an_approved_run_tells_the_people_who_prepared_it_why(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $this->post_('reverse', ['comment' => 'Housing levy was wrong'])->assertOk();

        $notice = $this->notified(self::HR_ID);
        $this->assertCount(1, $notice);
        $this->assertSame('Approved payroll reversed', $notice[0]->title);
        $this->assertStringContainsString('October 2026', $notice[0]->message);
        $this->assertStringContainsString('Housing levy was wrong', $notice[0]->message);
        $this->assertCount(0, $this->notified(self::ACCOUNTANT_ID));
    }

    public function test_discarding_a_draft_does_not_send_a_reversal_notice(): void
    {
        $this->run_(PayrollRun::STATUS_DRAFT, self::OTHER_HR_ID);
        PayrollSummary::where('period_id', self::PERIOD_ID)->update(['current_status_id' => 1]);
        $this->actingAsHr();

        $this->post_('reverse', ['comment' => 'Wrong salaries'])->assertOk();

        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_an_approved_month_cannot_be_reversed_under_a_later_month_that_has_results(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        DB::table('sys_hr_payroll_periods')->insert(['id' => 217, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'current_status_id' => 5]);
        PayrollSummary::create(['staff_id' => 1, 'period' => '2026-11-01', 'period_id' => 217, 'net_pay' => 50000, 'current_status_id' => 1]);
        $loan = HelbDeduction::create(['staff_id' => 1, 'loan_balance' => 1000, 'monthly_deduction' => 1000, 'cleared' => 0, 'suspended' => 0]);
        $this->actingAsAccountant();

        $this->post_('reverse', ['comment' => 'Too late'])->assertStatus(422)->assertJsonPath('error', fn ($error) => str_contains($error, 'later month'));

        $this->assertSame(PayrollRun::STATUS_APPROVED, PayrollRun::first()->status);
        $this->assertSame(2, PayrollSummary::where('period_id', self::PERIOD_ID)->count());
        $this->assertEquals(1000, $loan->fresh()->loan_balance);
    }

    // ── what the accountant sees ─────────────────────────────────────────

    private function reviewPage()
    {
        return $this->get(route('payroll.review', self::PERIOD_ID));
    }

    public function test_the_accountant_sees_the_checks_before_paying(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $page = $this->reviewPage()->assertOk();

        $page->assertSee('id="markPaidBtn"', false);
        $page->assertSee('Confirm payment: October 2026');
        $page->assertSee('<strong>2 staff</strong> are in this payroll, with a total net pay of <strong>KES 90,000.00</strong>', false);
        $page->assertSee('<strong>1 staff</strong> are in the EFT file, KES <strong>50,000.00</strong>', false);
        $page->assertSee('1 staff are not in the EFT file', false);
        $page->assertSee('Missing bank details: 1');
        $page->assertSee('No Bank Person (Missing bank details)');
        $page->assertSee('No EFT file has been downloaded for this payroll');
        $page->assertSee('id="payConfirm"', false);
        $page->assertSee('id="paySubmitBtn" disabled', false);
        $page->assertSee('It cannot be reversed afterwards');
    }

    public function test_the_checks_show_who_downloaded_the_eft_file(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();
        $this->get('/payroll/processing/' . self::PERIOD_ID . '/eft-test')->assertOk()->streamedContent();

        $page = $this->reviewPage()->assertOk();

        $page->assertSee('An EFT file was downloaded by Amina Accountant');
        $page->assertDontSee('No EFT file has been downloaded');
    }

    public function test_the_reverse_dialog_warns_about_a_downloaded_eft_file(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();
        $this->get('/payroll/processing/' . self::PERIOD_ID . '/eft-test')->assertOk()->streamedContent();

        $page = $this->reviewPage()->assertOk();

        $page->assertSee('Payslips for 2 staff will be withdrawn');
        $page->assertSee('An EFT file was downloaded by Amina Accountant');
        $page->assertSee('Make sure the bank has not processed it before you reverse');
    }

    public function test_mark_as_paid_is_not_offered_to_hr_or_to_the_person_who_processed_the_run(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsHr();
        $this->reviewPage()->assertOk()->assertDontSee('id="markPaidBtn"', false)->assertDontSee('id="payModal"', false)
            ->assertSee('The accountant confirms the bank payment next');

        $this->run_(PayrollRun::STATUS_APPROVED, self::ACCOUNTANT_ID);
        $this->actingAsAccountant();
        $this->reviewPage()->assertOk()->assertDontSee('id="markPaidBtn"', false)
            ->assertSee('another accountant must confirm the payment');
    }

    public function test_a_paid_run_shows_the_payment_and_offers_no_way_back(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();
        $this->pay(['payment_date' => '2026-11-16'])->assertOk();

        $page = $this->reviewPage()->assertOk();

        $page->assertSee('badge-light-primary">Paid', false);
        $page->assertSee('Paid on 16 Nov 2026 (confirmed by Amina Accountant)', false);
        $page->assertSee('Bank reference: <strong>EQB-20261117-001</strong>', false);
        $page->assertSee('can no longer be reversed');
        $page->assertSee('Marked as paid');
        $page->assertSee('Next month opened');
        $page->assertDontSee('id="markPaidBtn"', false);
        $page->assertDontSee('Reverse approved payroll');
        $page->assertSee('Download Test EFT File');
    }

    public function test_the_accountant_sees_what_changed_since_the_last_payroll_as_it_was_when_the_run_was_processed(): void
    {
        $snapshot = [
            'period' => 'October 2026',
            'since' => ['label' => 'the September 2026 payroll was processed', 'date' => '02 Oct 2026, 09:00'],
            'taken_at' => '04 Nov 2026, 10:00',
            'changes_total' => 1,
            'high_total' => 1,
            'warnings' => [],
            'groups' => [[
                'key' => 'contracts', 'kind' => 'change', 'title' => 'Contracts and salaries', 'hint' => 'Pay is worked out from the contract, so check these first.',
                'count' => 1, 'high' => 1, 'more' => 0,
                'rows' => [['who' => 'Paid Person', 'what' => 'Basic salary', 'detail' => '50,000.00 to 65,000.00', 'by' => 'Dan Director', 'at' => '10 Oct 2026, 10:02', 'high' => true]],
            ]],
        ];
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        PayrollRun::where('period_id', self::PERIOD_ID)->update(['changes_snapshot' => json_encode($snapshot)]);
        $this->actingAsAccountant();

        $page = $this->reviewPage()->assertOk();

        $page->assertSee('Changes since the last payroll');
        $page->assertSee('1 change since the last payroll');
        $page->assertSee('the September 2026 payroll was processed', false);
        $page->assertSee('50,000.00 to 65,000.00');
        $page->assertSee('by Dan Director');
        $page->assertSee('id="changesCard"', false);
    }

    public function test_a_run_with_no_kept_list_shows_no_changes_card(): void
    {
        $this->run_(PayrollRun::STATUS_WITH_ACCOUNTANT);
        $this->actingAsAccountant();

        $this->reviewPage()->assertOk()->assertDontSee('id="changesCard"', false);
    }

    public function test_the_script_posts_the_payment_with_the_figures_the_accountant_saw(): void
    {
        $this->run_(PayrollRun::STATUS_APPROVED);
        $this->actingAsAccountant();

        $page = $this->reviewPage()->assertOk()->getContent();
        $source = file_get_contents(resource_path('views/payroll/review.blade.php'));

        $this->assertStringContainsString('id="payNetTotal" value="90000"', $page);
        $this->assertStringContainsString('id="payEmployees" value="2"', $page);
        $this->assertStringContainsString("net_total: \$('#payNetTotal').val()", $source);
        $this->assertStringContainsString("employees: \$('#payEmployees').val()", $source);
        $this->assertStringContainsString("pay: \"{{ route('ajax.payroll.pay', \$period->id) }}\"", $source);
    }
}

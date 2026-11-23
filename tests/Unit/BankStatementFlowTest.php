<?php

namespace Tests\Unit;

use App\Events\BankStatementConfirmed;
use App\Exceptions\BankStatementException;
use App\Jobs\SendPaymentReceiptJob;
use App\Listeners\MatchBankStatementLines;
use App\Services\BankStatement\BankStatementConfirmationService;
use App\Services\BankStatement\BankStatementMatchService;
use App\Services\BankStatement\BankStatementPaymentService;
use App\Services\BankStatement\BankStatementUploadService;
use App\Services\PaymentAllocationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Mockery;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * An uploaded bank statement is staged, only a confirming role can confirm it, confirming returns at once and starts the
 * matching and the payments through a queued listener, and every line ends with the right result. The allocation itself
 * is Postgres SQL, so here it is a mock and the real one is run against the dev database; what is tested here is what
 * this feature hands it and what it does with the answer.
 */
class BankStatementFlowTest extends TestCase
{
    const UPLOADER = 11;
    const CONFIRMER = 10;
    const OUTSIDER = 12;
    const ROLE = 7;

    const HEADERS = ['TRAN_DATE', 'SECURITY NO.', 'TRAN_AMT', 'TRAN_TYPE', 'VALUE_DATE', 'REF_NUM', 'TRAN_RMKS', 'SECURITY NO 2', 'TRAN_PARTICULAR', 'MODE OF PAYMENT'];

    protected array $files = [];

    protected $allocation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Bus::fake([SendPaymentReceiptJob::class]);

        $this->allocation = Mockery::mock(PaymentAllocationService::class);
        $this->allocation->shouldReceive('allocatePaymentById')->andReturn(['allocations' => 1, 'cleared' => 1, 'partial' => 0, 'returns_updated' => 0])->byDefault();
        $this->app->instance(PaymentAllocationService::class, $this->allocation);

        Schema::create('approval_workflows', function ($table) {
            $table->increments('id');
            $table->string('workflow_name');
            $table->string('description')->nullable();
            $table->integer('status')->default(1);
            $table->integer('added_by')->nullable();
            $table->dateTime('added_on')->nullable();
        });
        Schema::create('approval_requests', function ($table) {
            $table->increments('id');
            $table->integer('workflow_id');
        });
        Schema::create('workflow_approvers', function ($table) {
            $table->increments('id');
            $table->integer('workflow_id');
            $table->integer('approver_id')->nullable();
            $table->integer('approval_level')->default(1);
            $table->integer('is_final')->default(0);
            $table->integer('is_active')->default(1);
            $table->integer('added_by')->nullable();
            $table->dateTime('added_on')->nullable();
            $table->string('position_type')->nullable();
            $table->integer('role_id')->nullable();
        });
        Schema::create('workflow_approvers_changes_log', function ($table) {
            $table->increments('id');
            $table->integer('workflow_id');
            $table->integer('approver_id')->nullable();
            $table->string('action');
            $table->integer('old_level')->nullable();
            $table->integer('new_level')->nullable();
            $table->integer('old_is_final')->nullable();
            $table->integer('new_is_final')->nullable();
            $table->text('details')->nullable();
            $table->integer('changed_by')->nullable();
            $table->dateTime('changed_on')->nullable();
        });
        Schema::create('cb_roles', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('display_name')->nullable();
        });
        Schema::create('cb_role_user', function ($table) {
            $table->integer('role_id');
            $table->integer('user_id');
        });
        Schema::create('josya_users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        Schema::create('sys_pm_units', function ($table) {
            $table->increments('id');
            $table->string('unit_code')->nullable();
            $table->integer('property_id')->nullable();
        });
        Schema::create('sys_pm_lease_agreements', function ($table) {
            $table->increments('id');
            $table->integer('unit_id');
            $table->integer('tenant_id')->nullable();
            $table->string('lease_status')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('sla_id')->nullable();
        });
        Schema::create('sys_pm_properties', function ($table) {
            $table->increments('id');
            $table->integer('client_id')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('field_officer_id')->nullable();
            $table->integer('zone_supervisor_id')->nullable();
            $table->integer('zone_manager_id')->nullable();
        });
        Schema::create('sys_pm_service_agreements', function ($table) {
            $table->increments('id');
            $table->integer('property_id')->nullable();
            $table->date('date_start')->nullable();
        });
        Schema::create('sys_pm_tenants_payments', function ($table) {
            $table->increments('id');
            $table->dateTime('date_time')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->string('reference')->nullable();
            $table->string('narration')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('lease_id')->nullable();
            $table->integer('tenant_id')->nullable();
            $table->integer('current_status_id')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('trans_details')->nullable();
            $table->integer('sla_id')->nullable();
            $table->string('receipt_number')->nullable();
            $table->integer('extract_id')->nullable();
            $table->integer('field_officer_id')->nullable();
            $table->integer('zone_supervisor_id')->nullable();
            $table->integer('zone_manager_id')->nullable();
        });

        // The real migration, so the tables here are the ones that ship
        (include database_path('migrations/2026_11_13_120000_create_bank_statement_tables.php'))->up();

        DB::table('cb_roles')->insert(['id' => self::ROLE, 'name' => 'accountant', 'display_name' => 'Accountant']);
        DB::table('cb_role_user')->insert(['role_id' => self::ROLE, 'user_id' => self::CONFIRMER]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * Build an Equity statement upload from credit rows of [unit code text, reference, amount].
     *
     * @param array $rows
     */
    private function equityFile(array $rows, string $name = 'nov.xlsx'): UploadedFile {
        $sheetRows = [self::HEADERS];
        foreach ($rows as $row) {
            [$particular, $reference, $amount] = $row;
            $date = $row[3] ?? 46336;
            $sheetRows[] = [$date, 'S1', $amount, 'CREDIT', $date, $reference, '', '', $particular, 'CASH'];
        }

        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($sheetRows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'stmt') . '.xlsx';
        (new Xlsx($book))->save($path);
        $this->files[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function unit(string $code, array $leaseStatuses): int {
        $unitId = DB::table('sys_pm_units')->insertGetId(['unit_code' => $code, 'property_id' => 5]);

        foreach ($leaseStatuses as $status) {
            DB::table('sys_pm_lease_agreements')->insert(['unit_id' => $unitId, 'tenant_id' => 900 + $unitId, 'lease_status' => $status]);
        }

        return $unitId;
    }

    private function setConfirmerRole(): void {
        DB::table('approval_workflows')->insert(['workflow_name' => 'bank_statement_confirmation']);
        app(BankStatementConfirmationService::class)->setConfirmerRoles([self::ROLE], 1);
    }

    public function test_upload_stages_every_line_and_refuses_the_same_file_twice(): void
    {
        $file = $this->equityFile([['187G-8/Akinyi', 'R1', 7000], ['no code here', 'R2', 500]]);
        $service = app(BankStatementUploadService::class);

        $id = $service->upload('equity', $file, self::UPLOADER);

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame('Uploaded', $upload->status);
        $this->assertSame(2, (int) $upload->line_count);
        $this->assertEquals(7500, $upload->total_amount);
        $this->assertSame(2, DB::table('cb_equity_statement_lines')->where('upload_id', $id)->count());
        Storage::disk('local')->assertExists($upload->file_path);

        try {
            $service->upload('equity', $file, self::UPLOADER);
            $this->fail('the same file went in twice');
        } catch (BankStatementException $e) {
            $this->assertStringContainsString('already uploaded as statement #' . $id, $e->getMessage());
        }
        $this->assertSame(1, DB::table('cb_bank_statement_uploads')->count());
    }

    public function test_a_wrong_template_stages_nothing(): void
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([['REFERENCE', 'AMOUNT'], ['A', 1]], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'stmt') . '.xlsx';
        (new Xlsx($book))->save($path);
        $this->files[] = $path;

        try {
            app(BankStatementUploadService::class)->upload('equity', new UploadedFile($path, 'x.xlsx', null, null, true), self::UPLOADER);
            $this->fail('a wrong template was accepted');
        } catch (BankStatementException $e) {
            $this->assertStringContainsString('does not look like the Equity Bank template', $e->getMessage());
        }

        $this->assertSame(0, DB::table('cb_bank_statement_uploads')->count());
        $this->assertSame(0, DB::table('cb_equity_statement_lines')->count());
    }

    public function test_an_unknown_bank_is_refused(): void
    {
        $this->expectException(BankStatementException::class);

        app(BankStatementUploadService::class)->upload('kcb', $this->equityFile([['187G-8', 'R1', 1]]), self::UPLOADER);
    }

    public function test_only_a_confirming_role_can_confirm_and_a_statement_is_confirmed_once(): void
    {
        $this->setConfirmerRole();
        Event::fake([BankStatementConfirmed::class]);
        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8', 'R1', 100]]), self::UPLOADER);
        $service = app(BankStatementConfirmationService::class);

        try {
            $service->confirm($id, self::OUTSIDER);
            $this->fail('an outsider confirmed');
        } catch (BankStatementException $e) {
            $this->assertStringContainsString('Only Accountant can confirm', $e->getMessage());
        }
        $this->assertSame('Uploaded', DB::table('cb_bank_statement_uploads')->find($id)->status);
        Event::assertNotDispatched(BankStatementConfirmed::class);

        $service->confirm($id, self::CONFIRMER);
        Event::assertDispatchedTimes(BankStatementConfirmed::class, 1);

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame('Confirmed', $upload->status, 'confirm itself only marks it; the listener does the matching');
        $this->assertSame(self::CONFIRMER, (int) $upload->confirmed_by);

        $this->expectException(BankStatementException::class);
        $this->expectExceptionMessage('already confirmed');
        $service->confirm($id, self::CONFIRMER);
    }

    /**
     * Whoever captured (uploaded) a statement cannot confirm it, even holding a confirming role: a second person has to.
     * The refusal leaves the statement untouched, and the other confirmer can still confirm it.
     */
    public function test_the_person_who_captured_a_statement_cannot_confirm_it(): void
    {
        $this->setConfirmerRole();
        DB::table('cb_role_user')->insert(['role_id' => self::ROLE, 'user_id' => self::UPLOADER]);
        Event::fake([BankStatementConfirmed::class]);
        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8', 'R1', 100]]), self::UPLOADER);
        $service = app(BankStatementConfirmationService::class);

        $this->assertTrue($service->canConfirm(self::UPLOADER), 'the uploader does hold a confirming role');

        try {
            $service->confirm($id, self::UPLOADER);
            $this->fail('the person who captured the statement confirmed it');
        } catch (BankStatementException $e) {
            $this->assertStringContainsString('You captured this statement, so someone else has to confirm it', $e->getMessage());
        }

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame('Uploaded', $upload->status);
        $this->assertNull($upload->confirmed_by);
        Event::assertNotDispatched(BankStatementConfirmed::class);

        $service->confirm($id, self::CONFIRMER);
        $this->assertSame('Confirmed', DB::table('cb_bank_statement_uploads')->find($id)->status);
        Event::assertDispatchedTimes(BankStatementConfirmed::class, 1);
    }

    public function test_nobody_can_confirm_until_the_roles_are_set(): void
    {
        DB::table('approval_workflows')->insert(['workflow_name' => 'bank_statement_confirmation']);
        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8', 'R1', 100]]), self::UPLOADER);

        $this->expectException(BankStatementException::class);
        $this->expectExceptionMessage('Only nobody (not set) can confirm');

        app(BankStatementConfirmationService::class)->confirm($id, self::CONFIRMER);
    }

    public function test_the_listener_is_not_queued_and_confirming_runs_it_to_match_the_lines(): void
    {
        $this->assertNotContains(ShouldQueue::class, class_implements(MatchBankStatementLines::class), 'matching runs inside the confirm request');

        $this->setConfirmerRole();
        $this->unit('187G-8', ['Active']);
        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/Akinyi', 'R1', 7000]]), self::UPLOADER);

        // The queue connection in tests is sync, so the queued listener runs inside confirm()
        app(BankStatementConfirmationService::class)->confirm($id, self::CONFIRMER);

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame('Completed', $upload->status, 'the listener matches and then pays');
        $this->assertSame(1, (int) $upload->matched_count);
        $this->assertSame(1, (int) $upload->paid_count);
        $this->assertNotNull($upload->matched_at);
        $this->assertSame(1, DB::table('sys_pm_tenants_payments')->count());
    }

    public function test_every_line_gets_the_right_result(): void
    {
        $this->setConfirmerRole();
        $active = $this->unit('187G-8', ['Terminated', 'Active']);
        $this->unit('76F-F4', ['Terminated']);
        $this->unit('55A-1', ['Active']);
        $this->unit('55A-2', ['Active']);
        // One code on two units, each with an Active lease
        $first = $this->unit('311B-49', ['Active']);
        $second = $this->unit('311B-49', ['Active']);
        DB::table('sys_pm_tenants_payments')->insert(['reference' => 'R-PAID', 'amount' => 900]);

        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([
            ['187G-8/Akinyi', 'R1', 7000],
            ['76F-F4/Vacant', 'R2', 100],
            ['999Z-9/Nobody', 'R3', 100],
            ['paid by mpesa UKAMP1YJSJ', 'R4', 100],
            ['311B-49/Two', 'R5', 100],
            ['187G-8/Again', 'R1', 7000],
            ['55A-1/Paid already', 'R-PAID', 900],
            ['55A-2/Same ref other amount', 'R1', 250],
        ]), self::UPLOADER);

        app(BankStatementConfirmationService::class)->confirm($id, self::CONFIRMER);

        $lines = DB::table('cb_equity_statement_lines')->where('upload_id', $id)->orderBy('row_no')->get();

        $this->assertSame('Paid', $lines[0]->match_status, 'a matched line is paid in straight away');
        $this->assertSame($active, (int) $lines[0]->unit_id);
        $this->assertSame(900 + $active, (int) $lines[0]->tenant_id);
        $this->assertSame(5, (int) $lines[0]->property_id);
        $this->assertNotNull($lines[0]->lease_id);

        $this->assertSame('Unmatched', $lines[1]->match_status);
        $this->assertStringContainsString('no Active lease', $lines[1]->match_note);
        $this->assertSame('Unmatched', $lines[2]->match_status);
        $this->assertStringContainsString('does not exist', $lines[2]->match_note);
        $this->assertSame('Unmatched', $lines[3]->match_status);
        $this->assertStringContainsString('No unit code', $lines[3]->match_note);
        $this->assertSame('Ambiguous', $lines[4]->match_status);
        $this->assertNull($lines[4]->lease_id);
        $this->assertSame('Duplicate', $lines[5]->match_status, 'same reference and amount as a line matched a moment ago in the same file');
        $this->assertSame('Duplicate', $lines[6]->match_status, 'already recorded as a payment');
        $this->assertSame('Paid', $lines[7]->match_status, 'the same reference with another amount is a different transaction');

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame(2, (int) $upload->matched_count);
        $this->assertSame(6, (int) $upload->unmatched_count);
    }

    public function test_a_statement_uploaded_again_in_a_new_file_does_not_match_the_same_transactions_twice(): void
    {
        $this->setConfirmerRole();
        $this->unit('187G-8', ['Active']);
        $uploads = app(BankStatementUploadService::class);
        $confirm = app(BankStatementConfirmationService::class);

        $first = $uploads->upload('equity', $this->equityFile([['187G-8/A', 'R1', 7000]], 'week1.xlsx'), self::UPLOADER);
        $confirm->confirm($first, self::CONFIRMER);

        // An overlapping statement: the same transaction plus a new one, so it is a different file
        $second = $uploads->upload('equity', $this->equityFile([['187G-8/A', 'R1', 7000], ['187G-8/A', 'R2', 7000]], 'week1-2.xlsx'), self::UPLOADER);
        $confirm->confirm($second, self::CONFIRMER);

        $lines = DB::table('cb_equity_statement_lines')->where('upload_id', $second)->orderBy('row_no')->get();
        $this->assertSame('Duplicate', $lines[0]->match_status);
        $this->assertSame('Paid', $lines[1]->match_status);
    }

    public function test_the_same_unit_paying_the_same_amount_next_month_is_not_a_duplicate_when_the_reference_is_the_unit_code(): void
    {
        $this->setConfirmerRole();
        $this->unit('187G-8', ['Active']);
        $uploads = app(BankStatementUploadService::class);
        $confirm = app(BankStatementConfirmationService::class);

        // REF_NUM is the unit code, so it repeats every month; 46336 is 10 Nov 2026 and 46366 is 10 Dec 2026
        $first = $uploads->upload('equity', $this->equityFile([['187G-8/A', '187G-8', 7000, 46336]], 'nov.xlsx'), self::UPLOADER);
        $confirm->confirm($first, self::CONFIRMER);
        $this->assertSame('Paid', DB::table('cb_equity_statement_lines')->where('upload_id', $first)->value('match_status'));

        // The next month, same unit, same amount: a new payment
        $second = $uploads->upload('equity', $this->equityFile([['187G-8/A', '187G-8', 7000, 46366]], 'dec.xlsx'), self::UPLOADER);
        $confirm->confirm($second, self::CONFIRMER);
        $this->assertSame('Paid', DB::table('cb_equity_statement_lines')->where('upload_id', $second)->value('match_status'));

        // The November transaction again in an overlapping file: a duplicate
        $third = $uploads->upload('equity', $this->equityFile([['187G-8/A', '187G-8', 7000, 46336], ['187G-8/A', '187G-8', 9000, 46336]], 'nov-again.xlsx'), self::UPLOADER);
        $confirm->confirm($third, self::CONFIRMER);
        $lines = DB::table('cb_equity_statement_lines')->where('upload_id', $third)->orderBy('row_no')->get();
        $this->assertSame('Duplicate', $lines[0]->match_status);
        $this->assertSame('Paid', $lines[1]->match_status, 'a different amount is a different transaction');
        $this->assertSame(3, DB::table('sys_pm_tenants_payments')->count());
    }

    public function test_a_failed_run_can_be_started_again_and_only_pending_lines_are_looked_at(): void
    {
        $this->setConfirmerRole();
        $this->unit('187G-8', ['Active']);
        Event::fake([BankStatementConfirmed::class]);
        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100]]), self::UPLOADER);
        $confirm = app(BankStatementConfirmationService::class);

        // Not failed yet: nothing to run again
        try {
            $confirm->retry($id, self::CONFIRMER);
            $this->fail('retry worked on a statement that had not failed');
        } catch (BankStatementException $e) {
            $this->assertStringContainsString('matching failed', $e->getMessage());
        }

        $confirm->confirm($id, self::CONFIRMER);
        app(MatchBankStatementLines::class)->failed(new BankStatementConfirmed($id), new \RuntimeException('boom'));

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame('Failed', $upload->status);
        $this->assertSame('boom', $upload->error);

        $confirm->retry($id, self::CONFIRMER);
        Event::assertDispatchedTimes(BankStatementConfirmed::class, 2);

        app(BankStatementMatchService::class)->matchUpload($id);
        $this->assertSame('Matched', DB::table('cb_bank_statement_uploads')->find($id)->status);

        // Running it again changes nothing: the upload is no longer Confirmed or Failed
        app(BankStatementMatchService::class)->matchUpload($id);
        $this->assertSame(1, DB::table('cb_equity_statement_lines')->where('match_status', 'Matched')->count());
    }

    public function test_only_an_unconfirmed_statement_can_be_deleted_and_its_file_goes_with_it(): void
    {
        $this->setConfirmerRole();
        Event::fake([BankStatementConfirmed::class]);
        $uploads = app(BankStatementUploadService::class);

        $gone = $uploads->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100]], 'a.xlsx'), self::UPLOADER);
        $path = DB::table('cb_bank_statement_uploads')->find($gone)->file_path;
        $uploads->delete($gone);

        $this->assertSame(0, DB::table('cb_bank_statement_uploads')->count());
        $this->assertSame(0, DB::table('cb_equity_statement_lines')->count());
        Storage::disk('local')->assertMissing($path);

        $kept = $uploads->upload('equity', $this->equityFile([['187G-8/A', 'R9', 100]], 'b.xlsx'), self::UPLOADER);
        app(BankStatementConfirmationService::class)->confirm($kept, self::CONFIRMER);

        $this->expectException(BankStatementException::class);
        $this->expectExceptionMessage('has not been confirmed');
        $uploads->delete($kept);
    }

    public function test_changing_the_confirming_roles_replaces_the_old_ones_and_is_logged(): void
    {
        DB::table('approval_workflows')->insert(['workflow_name' => 'bank_statement_confirmation']);
        DB::table('cb_roles')->insert(['id' => 8, 'name' => 'directors', 'display_name' => 'Directors']);
        $service = app(BankStatementConfirmationService::class);

        $service->setConfirmerRoles([self::ROLE], 1);
        $service->setConfirmerRoles([self::ROLE], 1);
        $this->assertSame(1, DB::table('workflow_approvers_changes_log')->where('action', 'APPROVER_ADDED')->count(), 'saving the same roles again changes nothing');

        $service->setConfirmerRoles([self::ROLE, 8], 1);
        $this->assertSame([self::ROLE, 8], collect($service->seats()['roles'])->sort()->values()->all());

        $service->setConfirmerRoles([8], 1);
        $this->assertSame([8], $service->seats()['roles']);
        $this->assertFalse($service->canConfirm(self::CONFIRMER), 'the accountant role was removed');

        $this->expectException(BankStatementException::class);
        $service->setConfirmerRoles([], 1);
    }

    /**
     * A property with two service agreements and a unit with an Active lease on it, ready to be paid into.
     */
    private function leasedUnit(string $code): array {
        DB::table('sys_pm_properties')->insert(['id' => 5, 'client_id' => 70, 'zone_id' => 3, 'field_officer_id' => 21, 'zone_supervisor_id' => 22, 'zone_manager_id' => 23]);
        DB::table('sys_pm_service_agreements')->insert(['id' => 101, 'property_id' => 5, 'date_start' => '2024-01-01']);
        DB::table('sys_pm_service_agreements')->insert(['id' => 102, 'property_id' => 5, 'date_start' => '2026-01-01']);

        $unitId = DB::table('sys_pm_units')->insertGetId(['unit_code' => $code, 'property_id' => 5]);
        $leaseId = DB::table('sys_pm_lease_agreements')->insertGetId(['unit_id' => $unitId, 'tenant_id' => 900, 'lease_status' => 'Active', 'client_id' => 70, 'property_id' => 5, 'zone_id' => null, 'sla_id' => null]);

        return [$unitId, $leaseId];
    }

    public function test_a_matched_line_becomes_a_payment_that_is_allocated_and_gets_a_receipt(): void
    {
        $this->setConfirmerRole();
        [$unitId, $leaseId] = $this->leasedUnit('187G-8');
        $paymentIds = [];
        $this->allocation->shouldReceive('allocatePaymentById')->once()->andReturnUsing(function ($id) use (&$paymentIds) {
            $paymentIds[] = $id;

            return ['allocations' => 2, 'cleared' => 2, 'partial' => 0, 'returns_updated' => 0];
        });

        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/Akinyi', 'R1', 7000]]), self::UPLOADER);
        app(BankStatementConfirmationService::class)->confirm($id, self::CONFIRMER);

        $payment = DB::table('sys_pm_tenants_payments')->first();
        $this->assertSame([(int) $payment->id], $paymentIds, 'the new payment is what gets allocated');
        $this->assertSame('R1', $payment->reference);
        $this->assertEquals(7000, $payment->amount);
        $this->assertSame(11, (int) $payment->current_status_id, 'unallocated until the allocation says otherwise');
        $this->assertSame(sprintf('RCT-%d-00001', now()->year), $payment->receipt_number);
        $this->assertSame($id, (int) $payment->extract_id);
        $this->assertSame(self::CONFIRMER, (int) $payment->created_by);
        $this->assertSame('2026-11-10', substr($payment->payment_date, 0, 10));
        $this->assertSame($leaseId, (int) $payment->lease_id);
        $this->assertSame($unitId, (int) $payment->unit_id);
        $this->assertSame(900, (int) $payment->tenant_id);
        $this->assertSame(5, (int) $payment->property_id);
        $this->assertSame(70, (int) $payment->client_id);
        $this->assertSame(3, (int) $payment->zone_id, 'the lease has no zone, so the property\'s is used');
        $this->assertSame([21, 22, 23], [(int) $payment->field_officer_id, (int) $payment->zone_supervisor_id, (int) $payment->zone_manager_id]);
        $this->assertSame(102, (int) $payment->sla_id, 'the lease has no sla_id, so the property\'s latest agreement is used, as invoices do');

        $line = DB::table('cb_equity_statement_lines')->first();
        $this->assertSame('Paid', $line->match_status);
        $this->assertSame((int) $payment->id, (int) $line->payment_id);
        $this->assertSame('Queued', $line->receipt_status);

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame('Completed', $upload->status);
        $this->assertSame(1, (int) $upload->paid_count);
        $this->assertEquals(7000, $upload->paid_amount);

        Bus::assertDispatched(SendPaymentReceiptJob::class, fn ($job) => $job->bank === 'equity' && $job->lineId === (int) $line->id && $job->paymentId === (int) $payment->id && $job->sentBy === self::CONFIRMER);
        Bus::assertDispatchedTimes(SendPaymentReceiptJob::class, 1);
    }

    public function test_receipt_numbers_run_on_across_lines_and_uploads(): void
    {
        $this->setConfirmerRole();
        $this->leasedUnit('187G-8');
        $uploads = app(BankStatementUploadService::class);
        $confirm = app(BankStatementConfirmationService::class);

        $first = $uploads->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100], ['187G-8/A', 'R2', 200]], 'a.xlsx'), self::UPLOADER);
        $confirm->confirm($first, self::CONFIRMER);
        $second = $uploads->upload('equity', $this->equityFile([['187G-8/A', 'R3', 300]], 'b.xlsx'), self::UPLOADER);
        $confirm->confirm($second, self::CONFIRMER);

        $this->assertSame(
            [sprintf('RCT-%d-00001', now()->year), sprintf('RCT-%d-00002', now()->year), sprintf('RCT-%d-00003', now()->year)],
            DB::table('sys_pm_tenants_payments')->orderBy('id')->pluck('receipt_number')->all()
        );
    }

    public function test_a_line_whose_allocation_fails_leaves_nothing_behind_and_the_others_still_go_through(): void
    {
        $this->setConfirmerRole();
        $this->leasedUnit('187G-8');
        $calls = 0;
        $this->allocation->shouldReceive('allocatePaymentById')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 1) {
                throw new \RuntimeException('GL account missing');
            }

            return ['allocations' => 1, 'cleared' => 1, 'partial' => 0, 'returns_updated' => 0];
        });

        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100], ['187G-8/A', 'R2', 200]]), self::UPLOADER);
        app(BankStatementConfirmationService::class)->confirm($id, self::CONFIRMER);

        $lines = DB::table('cb_equity_statement_lines')->orderBy('row_no')->get();
        $this->assertSame('Matched', $lines[0]->match_status, 'unpaid, so it can be run again');
        $this->assertNull($lines[0]->payment_id);
        $this->assertSame('Payment failed: GL account missing', $lines[0]->match_note);
        $this->assertSame('Paid', $lines[1]->match_status);

        $this->assertSame(1, DB::table('sys_pm_tenants_payments')->count(), 'the failed line\'s payment was rolled back with its allocation');
        $this->assertSame(['R2'], DB::table('sys_pm_tenants_payments')->pluck('reference')->all());
        $this->assertSame(sprintf('RCT-%d-00001', now()->year), DB::table('sys_pm_tenants_payments')->value('receipt_number'), 'the receipt number of the failed line was not used up');

        $upload = DB::table('cb_bank_statement_uploads')->find($id);
        $this->assertSame('Failed', $upload->status);
        $this->assertStringContainsString('1 line(s) could not be paid', $upload->error);
        $this->assertSame(1, (int) $upload->paid_count);
        Bus::assertDispatchedTimes(SendPaymentReceiptJob::class, 1);
    }

    public function test_running_a_failed_statement_again_pays_only_the_lines_not_yet_paid(): void
    {
        $this->setConfirmerRole();
        $this->leasedUnit('187G-8');
        $calls = 0;
        $this->allocation->shouldReceive('allocatePaymentById')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 1) {
                throw new \RuntimeException('boom');
            }

            return ['allocations' => 1, 'cleared' => 1, 'partial' => 0, 'returns_updated' => 0];
        });
        $confirm = app(BankStatementConfirmationService::class);

        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100], ['187G-8/A', 'R2', 200]]), self::UPLOADER);
        $confirm->confirm($id, self::CONFIRMER);
        $this->assertSame('Failed', DB::table('cb_bank_statement_uploads')->find($id)->status);

        // "Run again" fires the same event, and the queued listener does the work
        $confirm->retry($id, self::CONFIRMER);

        $this->assertSame('Completed', DB::table('cb_bank_statement_uploads')->find($id)->status);
        $this->assertSame(['R1', 'R2'], DB::table('sys_pm_tenants_payments')->orderBy('reference')->pluck('reference')->all(), 'R2 was not paid a second time');
        $this->assertSame(2, (int) DB::table('cb_bank_statement_uploads')->find($id)->paid_count);
        $this->assertSame(2, (int) DB::table('cb_bank_statement_uploads')->find($id)->matched_count);
        Bus::assertDispatchedTimes(SendPaymentReceiptJob::class, 2);
    }

    public function test_a_transaction_recorded_as_a_payment_while_waiting_is_not_paid_twice(): void
    {
        $this->setConfirmerRole();
        $this->leasedUnit('187G-8');
        Event::fake([BankStatementConfirmed::class]);
        $this->allocation->shouldReceive('allocatePaymentById')->never();

        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100]]), self::UPLOADER);
        app(BankStatementConfirmationService::class)->confirm($id, self::CONFIRMER);
        app(BankStatementMatchService::class)->matchUpload($id);
        $this->assertSame('Matched', DB::table('cb_equity_statement_lines')->value('match_status'));

        // Someone keys the same transaction in before the payment step runs
        DB::table('sys_pm_tenants_payments')->insert(['reference' => 'R1', 'amount' => 100]);
        app(BankStatementPaymentService::class)->payUpload($id);

        $this->assertSame('Duplicate', DB::table('cb_equity_statement_lines')->value('match_status'));
        $this->assertSame(1, DB::table('sys_pm_tenants_payments')->count());
        $this->assertSame(0, (int) DB::table('cb_bank_statement_uploads')->find($id)->paid_count);
        Bus::assertNotDispatched(SendPaymentReceiptJob::class);
    }

    public function test_paying_an_upload_twice_makes_one_payment(): void
    {
        $this->setConfirmerRole();
        $this->leasedUnit('187G-8');
        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100]]), self::UPLOADER);
        app(BankStatementConfirmationService::class)->confirm($id, self::CONFIRMER);

        app(BankStatementPaymentService::class)->payUpload($id);
        app(BankStatementPaymentService::class)->payUpload($id);

        $this->assertSame(1, DB::table('sys_pm_tenants_payments')->count());
        Bus::assertDispatchedTimes(SendPaymentReceiptJob::class, 1);
    }

    public function test_the_payment_is_only_made_for_a_confirmed_statement(): void
    {
        $this->setConfirmerRole();
        $this->leasedUnit('187G-8');
        $id = app(BankStatementUploadService::class)->upload('equity', $this->equityFile([['187G-8/A', 'R1', 100]]), self::UPLOADER);

        // Not confirmed: the status is still Uploaded, so neither step does anything
        app(BankStatementMatchService::class)->matchUpload($id);
        app(BankStatementPaymentService::class)->payUpload($id);

        $this->assertSame(0, DB::table('sys_pm_tenants_payments')->count());
        $this->assertSame('Pending', DB::table('cb_equity_statement_lines')->value('match_status'));
    }
}

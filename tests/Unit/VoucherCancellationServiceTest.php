<?php

namespace Tests\Unit;

use App\Models\ChequeLeaf;
use App\Models\PaymentVoucherHeader;
use App\Services\ChequeBookService;
use App\Services\ClientAdvanceService;
use App\Services\GlJournalPoster;
use App\Services\ReturnPostingService;
use App\Services\VoucherCancellationService;
use App\Services\VoucherNarrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class VoucherCancellationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cb_payment_vouchers_headers_new', function ($table) {
            $table->increments('id');
            $table->integer('voucher_sequence')->nullable();
            $table->string('voucher_number')->nullable();
            $table->string('voucher_status')->default('Pending');
            $table->string('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('cb_payment_vouchers_lines_new', function ($table) {
            $table->increments('id');
            $table->integer('entry_id')->nullable();
            $table->string('voucher_number')->nullable();
            $table->string('entry_number')->nullable();
            $table->string('voucher_type')->nullable();
            $table->integer('cheque_leaf_id')->nullable();
            $table->string('cheque_number')->nullable();
            $table->timestamps();
        });
        Schema::create('sys_fin_cheque_books', function ($table) {
            $table->increments('id');
            $table->integer('bank_account_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->integer('current_status_id')->default(1);
        });
        Schema::create('sys_fin_cheque_books_261_repeat', function ($table) {
            $table->increments('id');
            $table->integer('parent_id')->nullable();
            $table->string('cheque_number')->nullable();
            $table->integer('current_status_id')->default(1);
            $table->integer('bank_account_id')->nullable();
            $table->integer('locked_by_id')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->integer('used_by_id')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->string('used_for')->nullable();
            $table->integer('cancelled_by_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('sys_set_bank_accounts', function ($table) {
            $table->increments('id');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
        });
        Schema::create('josya_users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        Schema::create('approval_requests', function ($table) {
            $table->increments('id');
            $table->string('request_type')->nullable();
            $table->integer('request_id')->nullable();
            $table->integer('workflow_id')->nullable();
            $table->integer('current_level')->default(1);
            $table->integer('current_approver_id')->nullable();
            $table->string('status')->default('pending');
            $table->integer('initiated_by')->nullable();
            $table->timestamp('initiated_on')->nullable();
            $table->timestamp('completed_on')->nullable();
        });
        Schema::create('approval_requests_logs', function ($table) {
            $table->increments('id');
            $table->integer('approval_request_id');
            $table->integer('approver_id')->nullable();
            $table->integer('approval_level')->nullable();
            $table->string('action');
            $table->text('comment')->nullable();
            $table->integer('returned_to_level')->nullable();
            $table->timestamp('action_on')->nullable();
        });
        Schema::create('sys_pm_clients_advances_requisitions', function ($table) {
            $table->increments('id');
            $table->integer('voucher_id')->nullable();
        });
        Schema::create('cb_returns_summary', function ($table) {
            $table->increments('id');
            $table->integer('paid_status')->default(0);
            $table->integer('voucher_generated')->default(0);
        });
        // Cheque books and approval requests are audited, and the audit trail is not what these tests are about
        Schema::create('cb_audit_logs', function ($table) {
            $table->increments('id');
            $table->string('table_name')->nullable();
            $table->string('record_id')->nullable();
            $table->string('operation')->nullable();
            $table->text('old_data')->nullable();
            $table->text('new_data')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    private function service(): VoucherCancellationService
    {
        $advances = new ClientAdvanceService(new GlJournalPoster());

        return new VoucherCancellationService($advances, new ReturnPostingService(new GlJournalPoster(), $advances), new ChequeBookService());
    }

    private function leaf(int $bookId, string $number, int $status = ChequeLeaf::STATUS_USED): int
    {
        return DB::table('sys_fin_cheque_books_261_repeat')->insertGetId([
            'parent_id' => $bookId, 'cheque_number' => $number, 'current_status_id' => $status, 'bank_account_id' => 1,
        ]);
    }

    private function voucher(string $status = 'Pending', ?int $leafId = null, string $type = 'general_voucher'): PaymentVoucherHeader
    {
        $number = 'PV' . (DB::table('cb_payment_vouchers_headers_new')->count() + 1);
        $header = PaymentVoucherHeader::create(['voucher_number' => $number, 'voucher_status' => $status]);
        DB::table('cb_payment_vouchers_lines_new')->insert([
            'entry_id' => $header->id, 'voucher_number' => $number, 'voucher_type' => $type, 'cheque_leaf_id' => $leafId, 'entry_number' => '7',
        ]);

        return $header;
    }

    public function test_cancelling_a_pending_voucher_cancels_its_cheque_and_keeps_the_others(): void
    {
        $book = DB::table('sys_fin_cheque_books')->insertGetId(['reference_number' => 'CB-1']);
        $mine = $this->leaf($book, '100001');
        $other = $this->leaf($book, '100002');
        $left = $this->leaf($book, '100003', ChequeLeaf::STATUS_AVAILABLE);
        $voucher = $this->voucher('Pending', $mine);

        $cancelled = $this->service()->cancel($voucher, 5, 'Amos', 'Cheque spoiled at printing');

        $this->assertSame(1, $cancelled);
        $voucher->refresh();
        $this->assertSame('Cancelled', $voucher->voucher_status);
        $this->assertSame('Amos', $voucher->cancelled_by);
        $this->assertSame('Cheque spoiled at printing', $voucher->cancel_reason);
        $this->assertNotNull($voucher->cancelled_at);

        $leaf = DB::table('sys_fin_cheque_books_261_repeat')->find($mine);
        $this->assertSame(ChequeLeaf::STATUS_CANCELLED, (int) $leaf->current_status_id);
        $this->assertSame(5, (int) $leaf->cancelled_by_id);
        $this->assertStringContainsString($voucher->voucher_number, $leaf->cancel_reason);
        $this->assertSame(ChequeLeaf::STATUS_USED, (int) DB::table('sys_fin_cheque_books_261_repeat')->find($other)->current_status_id);
        $this->assertSame(ChequeLeaf::STATUS_AVAILABLE, (int) DB::table('sys_fin_cheque_books_261_repeat')->find($left)->current_status_id);
    }

    public function test_an_approved_voucher_with_no_posted_payout_can_be_cancelled(): void
    {
        $book = DB::table('sys_fin_cheque_books')->insertGetId(['reference_number' => 'CB-1']);
        $voucher = $this->voucher('Approved', $this->leaf($book, '200001'));

        $this->service()->cancel($voucher, 5, 'Amos', 'Wrong payee on the cheque');

        $this->assertSame('Cancelled', $voucher->fresh()->voucher_status);
    }

    public function test_an_approved_return_or_advance_voucher_is_refused_and_nothing_changes(): void
    {
        $book = DB::table('sys_fin_cheque_books')->insertGetId(['reference_number' => 'CB-1']);

        foreach (['client_returns', ClientAdvanceService::VOUCHER_TYPE] as $type) {
            $leaf = $this->leaf($book, 'R' . $type);
            $voucher = $this->voucher('Approved', $leaf, $type);

            try {
                $this->service()->cancel($voucher, 5, 'Amos', 'Trying to cancel a paid out voucher');
                $this->fail('An approved ' . $type . ' voucher must not be cancelled');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('payout is already', $e->getMessage());
            }

            $this->assertSame('Approved', $voucher->fresh()->voucher_status);
            $this->assertSame(ChequeLeaf::STATUS_USED, (int) DB::table('sys_fin_cheque_books_261_repeat')->find($leaf)->current_status_id);
        }
    }

    public function test_a_pending_return_voucher_can_be_cancelled_and_frees_the_return(): void
    {
        $book = DB::table('sys_fin_cheque_books')->insertGetId(['reference_number' => 'CB-1']);
        $returnId = DB::table('cb_returns_summary')->insertGetId(['paid_status' => 0, 'voucher_generated' => 1]);
        $voucher = $this->voucher('Pending', $this->leaf($book, '300001'), 'client_returns');
        DB::table('cb_payment_vouchers_lines_new')->where('entry_id', $voucher->id)->update(['entry_number' => (string) $returnId]);

        $this->service()->cancel($voucher, 5, 'Amos', 'Raised against the wrong return');

        $this->assertSame(0, (int) DB::table('cb_returns_summary')->find($returnId)->voucher_generated);
    }

    public function test_a_pending_advance_voucher_can_be_cancelled_and_frees_the_advance(): void
    {
        $advanceId = DB::table('sys_pm_clients_advances_requisitions')->insertGetId(['voucher_id' => null]);
        $voucher = $this->voucher('Pending', null, ClientAdvanceService::VOUCHER_TYPE);
        DB::table('sys_pm_clients_advances_requisitions')->where('id', $advanceId)->update(['voucher_id' => $voucher->id]);
        DB::table('cb_payment_vouchers_lines_new')->where('entry_id', $voucher->id)->update(['entry_number' => (string) $advanceId]);

        $this->service()->cancel($voucher, 5, 'Amos', 'Advance paid in cash instead');

        $this->assertNull(DB::table('sys_pm_clients_advances_requisitions')->find($advanceId)->voucher_id);
    }

    public function test_a_waiting_approval_is_closed_with_the_cancellation_on_its_log(): void
    {
        $voucher = $this->voucher('Pending');
        $requestId = DB::table('approval_requests')->insertGetId([
            'request_type' => 'voucher', 'request_id' => $voucher->id, 'status' => 'pending', 'current_level' => 2, 'current_approver_id' => 9,
        ]);

        $this->service()->cancel($voucher, 5, 'Amos', 'Duplicate of another voucher');

        $request = DB::table('approval_requests')->find($requestId);
        $this->assertSame('rejected', $request->status);
        $this->assertNull($request->current_approver_id);
        $log = DB::table('approval_requests_logs')->where('approval_request_id', $requestId)->first();
        $this->assertStringContainsString('Duplicate of another voucher', $log->comment);
        $this->assertSame(2, (int) $log->approval_level);
    }

    public function test_a_cancelled_voucher_cannot_be_cancelled_again_and_a_short_reason_is_refused(): void
    {
        $voucher = $this->voucher('Pending');

        try {
            $this->service()->cancel($voucher, 5, 'Amos', 'no');
            $this->fail('A reason under 5 characters must be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }
        $this->assertSame('Pending', $voucher->fresh()->voucher_status);

        $this->service()->cancel($voucher, 5, 'Amos', 'Entered twice by mistake');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already cancelled');
        $this->service()->cancel($voucher->fresh(), 5, 'Amos', 'Entered twice by mistake');
    }

    public function test_the_cancelled_cheques_list_shows_the_voucher_bank_and_who_cancelled(): void
    {
        DB::table('sys_set_bank_accounts')->insert(['id' => 1, 'account_name' => 'CO-OP BUSINESS', 'account_number' => '0112']);
        DB::table('josya_users')->insert(['id' => 5, 'name' => 'Amos']);
        $book = DB::table('sys_fin_cheque_books')->insertGetId(['reference_number' => 'CB-9', 'bank_account_id' => 1]);
        $voucher = $this->voucher('Pending', $this->leaf($book, '400001'));
        $this->leaf($book, '400002'); // used, not cancelled: must not be listed

        $this->service()->cancel($voucher, 5, 'Amos', 'Cheque lost before printing');

        $rows = $this->service()->cancelledCheques();

        $this->assertCount(1, $rows);
        $this->assertSame('400001', $rows[0]['cheque_number']);
        $this->assertSame($voucher->voucher_number, $rows[0]['voucher_number']);
        $this->assertSame('CO-OP BUSINESS (0112)', $rows[0]['bank_account']);
        $this->assertSame('CB-9', $rows[0]['book_reference']);
        $this->assertSame('Amos', $rows[0]['cancelled_by']);
        $this->assertStringContainsString('Cheque lost before printing', $rows[0]['reason']);

        $this->assertCount(1, $this->service()->cancelledCheques(['search' => $voucher->voucher_number]));
        $this->assertCount(0, $this->service()->cancelledCheques(['search' => 'NOT-THERE']));
        $this->assertCount(0, $this->service()->cancelledCheques(['bank_account_id' => 2]));
    }

    public function test_narration_is_the_standard_text_followed_by_the_unit_in_capitals(): void
    {
        $this->assertSame('WATER BILL PLOT 14', VoucherNarrations::compose('WATER BILL', 'plot 14'));
        $this->assertSame('CHEQUE CHARGES', VoucherNarrations::compose('CHEQUE CHARGES'));
        $this->assertSame('BANK LEVY A3', VoucherNarrations::compose(VoucherNarrations::OTHER, 'a3', 'bank levy'));
        $this->assertSame('', VoucherNarrations::compose('', ''));
    }
}

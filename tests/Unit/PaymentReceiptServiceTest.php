<?php

namespace Tests\Unit;

use App\Services\BankStatement\PaymentReceiptService;
use App\Services\Comms\EmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * The receipt of a payment that came in through a bank statement goes to the tenant through EmailService (so it is
 * logged), carries the PDF, and the result lands on the statement line. A tenant with no usable email gets no email and
 * the line says so.
 */
class PaymentReceiptServiceTest extends TestCase
{
    const TENANT = 900;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_pm_tenants_payments', function ($table) {
            $table->increments('id');
            $table->integer('tenant_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('reference')->nullable();
            $table->string('receipt_number')->nullable();
            $table->integer('extract_id')->nullable();
        });
        Schema::create('sys_pm_clients', function ($table) {
            $table->increments('id');
            $table->string('names')->nullable();
            $table->string('email_address')->nullable();
        });
        Schema::create('sys_pm_units', function ($table) {
            $table->increments('id');
            $table->string('unit_code')->nullable();
        });
        Schema::create('sys_pm_properties', function ($table) {
            $table->increments('id');
            $table->string('property_name')->nullable();
        });
        Schema::create('sys_pm_debtors_invoices', function ($table) {
            $table->increments('id');
            $table->string('reference_number')->nullable();
            $table->string('narration')->nullable();
            $table->dateTime('date_due')->nullable();
            $table->dateTime('returns_period')->nullable();
            $table->integer('item_type_id')->nullable();
            $table->string('return_type_id')->nullable();
        });
        Schema::create('sys_set_invoiceable_items', function ($table) {
            $table->increments('id');
            $table->string('item_name')->nullable();
        });
        Schema::create('sys_pm_tenants_payments_invoices', function ($table) {
            $table->increments('id');
            $table->integer('payment_id');
            $table->integer('invoice_id');
            $table->decimal('amount_allocated', 15, 2);
            $table->decimal('new_invoice_balance', 15, 2);
        });

        (include database_path('migrations/2026_11_13_120000_create_bank_statement_tables.php'))->up();

        DB::table('sys_pm_units')->insert(['id' => 1, 'unit_code' => '187G-8']);
        DB::table('sys_pm_properties')->insert(['id' => 5, 'property_name' => 'CASA MUNET']);
        DB::table('cb_bank_statement_uploads')->insert(['id' => 1, 'bank' => 'equity', 'file_name' => 'a.xlsx', 'file_path' => 'x', 'file_hash' => 'h', 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * A payment of the given amount with one allocation, one statement line, and a tenant with the given email.
     *
     * @return array{int, int} the payment id and the statement line id
     */
    private function payment(?string $email, float $amount = 7000.0, float $allocated = 7000.0): array {
        DB::table('sys_pm_clients')->insert(['id' => self::TENANT, 'names' => 'NAO AUTO SPARES', 'email_address' => $email]);
        $paymentId = DB::table('sys_pm_tenants_payments')->insertGetId(['tenant_id' => self::TENANT, 'unit_id' => 1, 'property_id' => 5, 'payment_date' => '2026-11-10 00:00:00', 'amount' => $amount, 'reference' => 'R1', 'receipt_number' => 'RCT-2026-00007', 'extract_id' => 1]);
        $invoiceId = DB::table('sys_pm_debtors_invoices')->insertGetId(['reference_number' => 'INV-1', 'narration' => 'RENT FOR October 2026', 'date_due' => '2026-10-01', 'returns_period' => '2026-10-01']);
        DB::table('sys_pm_tenants_payments_invoices')->insert(['payment_id' => $paymentId, 'invoice_id' => $invoiceId, 'amount_allocated' => $allocated, 'new_invoice_balance' => 250]);
        $lineId = DB::table('cb_equity_statement_lines')->insertGetId(['upload_id' => 1, 'row_no' => 2, 'payment_id' => $paymentId, 'match_status' => 'Paid', 'receipt_status' => 'Queued', 'created_at' => now(), 'updated_at' => now()]);

        return [$paymentId, $lineId];
    }

    public function test_the_receipt_is_emailed_through_the_email_service_with_the_pdf_and_logged_against_the_payment(): void
    {
        [$paymentId, $lineId] = $this->payment('tenant@example.com');
        $captured = null;
        $email = Mockery::mock(EmailService::class);
        $email->shouldReceive('send')->once()->withArgs(function (...$args) use (&$captured) {
            $captured = $args;

            return true;
        })->andReturn([true, null]);

        $status = (new PaymentReceiptService($email))->send('equity', $lineId, $paymentId, 10);

        $this->assertSame('Sent', $status);
        $this->assertSame('Sent', DB::table('cb_equity_statement_lines')->where('id', $lineId)->value('receipt_status'));

        [$to, $name, $subject, $body, $type, $batch, $recipientType, $recipientId, $attachPath, $attachName, $raw, $rawName, $mime, $entityType, $entityId, $sentBy] = $captured;
        $this->assertSame('tenant@example.com', $to);
        $this->assertSame('NAO AUTO SPARES', $name);
        $this->assertSame('Payment receipt RCT-2026-00007', $subject);
        $this->assertStringContainsString('RCT-2026-00007', $body);
        $this->assertStringContainsString('7,000.00', $body);
        $this->assertSame('receipt', $type);
        $this->assertNull($batch);
        $this->assertSame('tenant', $recipientType);
        $this->assertSame(self::TENANT, $recipientId);
        $this->assertNull($attachPath);
        $this->assertStringStartsWith('%PDF', $raw);
        $this->assertSame('Receipt_RCT-2026-00007.pdf', $rawName);
        $this->assertSame('application/pdf', $mime);
        $this->assertSame('payment', $entityType);
        $this->assertSame($paymentId, $entityId);
        $this->assertSame(10, $sentBy);
    }

    public function test_a_send_that_fails_is_shown_as_failed_on_the_line(): void
    {
        [$paymentId, $lineId] = $this->payment('tenant@example.com');
        $email = Mockery::mock(EmailService::class);
        $email->shouldReceive('send')->once()->andReturn([false, 'SMTP down']);

        $this->assertSame('Failed', (new PaymentReceiptService($email))->send('equity', $lineId, $paymentId));
        $this->assertSame('Failed', DB::table('cb_equity_statement_lines')->where('id', $lineId)->value('receipt_status'));
    }

    public function test_a_tenant_with_no_usable_email_gets_no_email_and_the_line_says_so(): void
    {
        foreach ([null, '', 'not an email'] as $bad) {
            DB::table('sys_pm_clients')->delete();
            DB::table('sys_pm_tenants_payments')->delete();
            DB::table('cb_equity_statement_lines')->delete();
            [$paymentId, $lineId] = $this->payment($bad);

            $email = Mockery::mock(EmailService::class);
            $email->shouldReceive('send')->never();

            $this->assertSame('NoEmail', (new PaymentReceiptService($email))->send('equity', $lineId, $paymentId), 'email: ' . var_export($bad, true));
            $this->assertSame('NoEmail', DB::table('cb_equity_statement_lines')->where('id', $lineId)->value('receipt_status'));
        }
    }

    public function test_the_receipt_shows_what_was_applied_and_what_is_held_as_credit(): void
    {
        [$paymentId] = $this->payment('tenant@example.com', 5000.0, 3000.0);

        $data = (new PaymentReceiptService(Mockery::mock(EmailService::class)))->receiptData($paymentId);

        $this->assertSame('RCT-2026-00007', $data['receipt_number']);
        $this->assertSame('10 Nov 2026', $data['payment_date']);
        $this->assertSame('Equity Bank', $data['bank']);
        $this->assertSame('187G-8', $data['unit']);
        $this->assertSame('CASA MUNET', $data['property']);
        $this->assertSame(3000.0, $data['allocated_total']);
        $this->assertSame(2000.0, $data['credit']);
        $this->assertCount(1, $data['allocations']);
        $this->assertSame('RENT FOR October 2026', $data['allocations'][0]['description']);
        $this->assertSame(250.0, $data['allocations'][0]['balance']);
    }
}

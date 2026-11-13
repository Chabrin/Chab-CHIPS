<?php

namespace Tests\Unit;

use App\Exceptions\BankStatementException;
use App\Jobs\SendPaymentReceiptJob;
use App\Services\BankStatement\BankStatementConfirmationService;
use App\Services\BankStatement\BankStatementManualMatchService;
use App\Services\PaymentAllocationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Matching a line by hand: the lookups behind the four steps (client, property, unit, tenant), who may do it, and the
 * match-and-pay itself, which saves the match and the payment together or not at all. The allocation is Postgres SQL, so
 * here it is a mock and the real one is run against the dev database.
 */
class BankStatementManualMatchTest extends TestCase
{
    const UPLOADER = 11;
    const MATCHER = 10;
    const OUTSIDER = 12;
    const ROLE = 7;

    protected $allocation;

    /**
     * Create the tables the matching reads and writes, a landlord with one property, one unit and two leases (one Active,
     * one ended), a Completed statement captured by someone else, and make one role the confirmer.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SendPaymentReceiptJob::class]);

        $this->allocation = Mockery::mock(PaymentAllocationService::class);
        $this->allocation->shouldReceive('allocatePaymentById')->andReturnUsing(function ($paymentId) {
            DB::table('sys_pm_tenants_payments_invoices')->insert(['payment_id' => $paymentId, 'invoice_id' => 1, 'amount_allocated' => 5000]);

            return ['allocations' => 1, 'cleared' => 1, 'partial' => 0, 'returns_updated' => 0];
        })->byDefault();
        $this->app->instance(PaymentAllocationService::class, $this->allocation);

        Schema::create('approval_workflows', function ($table) {
            $table->increments('id');
            $table->string('workflow_name');
            $table->string('description')->nullable();
            $table->integer('status')->default(1);
            $table->integer('added_by')->nullable();
            $table->dateTime('added_on')->nullable();
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
        Schema::create('sys_pm_clients', function ($table) {
            $table->increments('id');
            $table->string('names')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('type_id')->nullable();
        });
        Schema::create('sys_pm_properties', function ($table) {
            $table->increments('id');
            $table->integer('client_id')->nullable();
            $table->string('property_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('field_officer_id')->nullable();
            $table->integer('zone_supervisor_id')->nullable();
            $table->integer('zone_manager_id')->nullable();
        });
        Schema::create('sys_pm_units', function ($table) {
            $table->increments('id');
            $table->integer('property_id')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('unit_name')->nullable();
            $table->string('unit_number')->nullable();
        });
        Schema::create('sys_pm_lease_agreements', function ($table) {
            $table->increments('id');
            $table->integer('unit_id');
            $table->integer('tenant_id')->nullable();
            $table->string('lease_status')->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->integer('client_id')->nullable();
            $table->integer('property_id')->nullable();
            $table->integer('zone_id')->nullable();
            $table->integer('sla_id')->nullable();
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
        Schema::create('sys_pm_tenants_payments_invoices', function ($table) {
            $table->increments('id');
            $table->integer('payment_id');
            $table->integer('invoice_id');
            $table->decimal('amount_allocated', 15, 2);
        });

        // The real migrations, so the tables here are the ones that ship
        (include database_path('migrations/2026_11_13_120000_create_bank_statement_tables.php'))->up();
        (include database_path('migrations/2026_11_13_160000_add_manual_match_columns_to_bank_statement_lines.php'))->up();

        DB::table('cb_roles')->insert(['id' => self::ROLE, 'name' => 'accountant', 'display_name' => 'Accountant']);
        DB::table('cb_role_user')->insert(['role_id' => self::ROLE, 'user_id' => self::MATCHER]);
        DB::table('josya_users')->insert([['id' => self::MATCHER, 'name' => 'Mark'], ['id' => self::UPLOADER, 'name' => 'Amos']]);
        app(BankStatementConfirmationService::class)->setConfirmerRoles([self::ROLE], 1);

        DB::table('sys_pm_clients')->insert([
            ['id' => 1, 'names' => 'CASA MUNET LTD', 'reference_number' => 'LAN-001', 'type_id' => '1'],
            ['id' => 2, 'names' => 'ZEBRA HOLDINGS', 'reference_number' => 'LAN-002', 'type_id' => '1'],
            ['id' => 901, 'names' => 'NAO AUTO SPARES', 'reference_number' => 'TEN-901', 'type_id' => '2'],
            ['id' => 902, 'names' => 'CASA TENANT FORMER', 'reference_number' => 'TEN-902', 'type_id' => '2'],
        ]);
        DB::table('sys_pm_properties')->insert([['id' => 5, 'client_id' => 1, 'property_name' => 'CASA MUNET', 'reference_number' => 'PRO-5'], ['id' => 6, 'client_id' => 2, 'property_name' => 'ZEBRA PLAZA', 'reference_number' => 'PRO-6']]);
        DB::table('sys_pm_units')->insert([
            ['id' => 7, 'property_id' => 5, 'unit_code' => '187G-8', 'unit_name' => 'Shop 8', 'unit_number' => '8'],
            ['id' => 8, 'property_id' => 5, 'unit_code' => '187G-9', 'unit_name' => 'Shop 9', 'unit_number' => '9'],
        ]);
        DB::table('sys_pm_lease_agreements')->insert([
            ['id' => 101, 'unit_id' => 7, 'tenant_id' => 901, 'lease_status' => 'Active', 'date_start' => '2025-01-01', 'date_end' => null, 'client_id' => 1, 'property_id' => 5],
            ['id' => 102, 'unit_id' => 7, 'tenant_id' => 902, 'lease_status' => 'Terminated', 'date_start' => '2023-01-01', 'date_end' => '2024-12-31', 'client_id' => 1, 'property_id' => 5],
        ]);

        DB::table('cb_bank_statement_uploads')->insert(['id' => 1, 'bank' => 'equity', 'file_name' => 'nov.xlsx', 'file_path' => 'x', 'file_hash' => 'h1', 'status' => 'Completed', 'uploaded_by' => self::UPLOADER, 'confirmed_by' => 20, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Add a statement line to upload 1.
     *
     * @param string $status its result
     * @param int $row its row number
     * @param string $reference the bank reference
     * @param float $amount
     */
    private function line(string $status = 'Unmatched', int $row = 2, string $reference = 'R1', float $amount = 7000.0): int {
        return DB::table('cb_equity_statement_lines')->insertGetId([
            'upload_id' => 1, 'row_no' => $row, 'tran_date' => '2026-11-10', 'tran_amt' => $amount, 'ref_num' => $reference,
            'tran_particular' => 'MPESA PAYBILL Akinyi', 'match_status' => $status, 'match_note' => 'No unit code found in the line.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * The service under test.
     */
    private function service(): BankStatementManualMatchService {
        return app(BankStatementManualMatchService::class);
    }

    /**
     * Step 1 lists clients (landlords) only, found by name or reference without case or wildcards.
     */
    public function test_clients_are_found_by_name_or_reference_and_tenants_are_left_out(): void
    {
        $byName = $this->service()->clients('casa');
        $this->assertSame(['CASA MUNET LTD'], array_column($byName['data'], 'names'), 'the tenant CASA TENANT FORMER is not a client');
        $this->assertSame(1, $byName['total']);
        $this->assertSame(1, $byName['data'][0]['properties']);

        $this->assertSame(['ZEBRA HOLDINGS'], array_column($this->service()->clients('lan-002')['data'], 'names'), 'by reference, whatever the case');
        $this->assertSame(['CASA MUNET LTD', 'ZEBRA HOLDINGS'], array_column($this->service()->clients()['data'], 'names'), 'no text: every client by name');
        $this->assertSame([], $this->service()->clients('%')['data'], 'a percent sign is matched as typed, not as a wildcard');
    }

    /**
     * Step 2 lists the client's properties; step 3 lists only the Active leases of the chosen property, a unit with its
     * tenant in each row, never the vacant units, and the ended leases only when asked for.
     */
    public function test_a_clients_properties_and_then_only_the_active_leases_of_the_property_are_listed(): void
    {
        $properties = $this->service()->properties(1);
        $this->assertSame(['CASA MUNET'], array_column($properties['data'], 'name'), 'only this client\'s properties');
        $this->assertSame(2, $properties['data'][0]['units']);
        $this->assertSame([], $this->service()->properties(1, 'zebra')['data']);

        $leases = $this->service()->leases(5);
        $this->assertSame(1, $leases['total']);
        $this->assertSame([101], array_column($leases['data'], 'id'), 'the vacant unit 187G-9 and the lease that ended are not listed');
        $this->assertSame(['id' => 101, 'unit_id' => 7, 'unit_code' => '187G-8', 'unit_name' => 'Shop 8', 'unit_number' => '8', 'tenant_id' => 901, 'tenant' => 'NAO AUTO SPARES', 'reference' => 'TEN-901', 'status' => 'Active', 'start' => '2025-01-01', 'end' => null], $leases['data'][0]);

        $withEnded = $this->service()->leases(5, '', true);
        $this->assertSame([101, 102], array_column($withEnded['data'], 'id'), 'Active first, then the one that ended');
        $this->assertSame(['Active', 'Terminated'], array_column($withEnded['data'], 'status'));
        $this->assertSame('2024-12-31', $withEnded['data'][1]['end']);

        foreach (['187g-8', 'shop 8', 'nao auto', 'TEN-901'] as $term) {
            $this->assertSame([101], array_column($this->service()->leases(5, $term)['data'], 'id'), 'found by ' . $term);
        }
        $this->assertSame([], $this->service()->leases(5, 'former')['data'], 'a tenant who left is not found unless ended leases are included');
        $this->assertSame([102], array_column($this->service()->leases(5, 'former', true)['data'], 'id'));
        $this->assertSame([], $this->service()->leases(5, 'zebra')['data']);

        DB::table('sys_pm_units')->insert(['id' => 9, 'property_id' => 6, 'unit_code' => 'Z-1']);
        DB::table('sys_pm_lease_agreements')->insert(['id' => 103, 'unit_id' => 9, 'tenant_id' => 902, 'lease_status' => 'Active', 'property_id' => 6]);
        $this->assertSame([103], array_column($this->service()->leases(6)['data'], 'id'), 'each property lists its own leases');
        $this->assertSame([101], array_column($this->service()->leases(5)['data'], 'id'));
    }

    /**
     * A unit code that names exactly one unit starts the page at the tenant step.
     */
    public function test_a_unit_code_that_points_to_one_unit_gives_the_whole_path(): void
    {
        $suggestion = $this->service()->suggestion('187G-8');

        $this->assertSame(['id' => 1, 'names' => 'CASA MUNET LTD', 'reference' => 'LAN-001'], $suggestion['client']);
        $this->assertSame(5, $suggestion['property']['id']);
        $this->assertSame(7, $suggestion['unit']['id']);

        $this->assertNull($this->service()->suggestion('NO-SUCH'));
        $this->assertNull($this->service()->suggestion(null));

        DB::table('sys_pm_units')->insert(['id' => 9, 'property_id' => 6, 'unit_code' => '187G-8']);
        $this->assertNull($this->service()->suggestion('187G-8'), 'two units share the code, so it cannot say which');
    }

    /**
     * Who may match: a confirmer, not the capturer, once the run has finished; and which line results can be matched.
     */
    public function test_only_a_confirmer_who_did_not_capture_the_statement_may_match_once_its_run_has_finished(): void
    {
        $service = $this->service();

        $this->assertNull($service->refusal('Completed', self::UPLOADER, self::MATCHER));
        $this->assertNull($service->refusal('Failed', self::UPLOADER, self::MATCHER));
        $this->assertStringContainsString('Only Accountant can match', $service->refusal('Completed', self::UPLOADER, self::OUTSIDER));
        $this->assertStringContainsString('You captured this statement', $service->refusal('Completed', self::MATCHER, self::MATCHER));
        $this->assertStringContainsString('finished its run. It is paying now', $service->refusal('Paying', self::UPLOADER, self::MATCHER));

        $this->assertNull($service->lineRefusal('Unmatched'));
        $this->assertNull($service->lineRefusal('Ambiguous'));
        foreach (['Paid', 'Matched', 'Duplicate', 'Invalid', 'Skipped', 'Pending'] as $status) {
            $this->assertStringContainsString('Only Unmatched or Ambiguous', $service->lineRefusal($status), $status);
        }
    }

    /**
     * The page gets the line, why it did not match, whether this user may match it, and the unit suggestion.
     */
    public function test_the_page_has_the_line_the_reason_and_the_units_suggestion(): void
    {
        $id = $this->line();
        DB::table('cb_equity_statement_lines')->where('id', $id)->update(['unit_code' => '187G-8', 'payer_name' => 'Akinyi', 'match_note' => 'Unit 187G-8 has no Active lease.']);

        $page = $this->service()->page('equity', $id, self::MATCHER);

        $this->assertSame('Equity Bank', $page['bank_label']);
        $this->assertSame('nov.xlsx', $page['file_name']);
        $this->assertSame('7,000.00', $page['amount']);
        $this->assertSame('2026-11-10', $page['date']);
        $this->assertSame('R1', $page['reference']);
        $this->assertSame('Unit 187G-8 has no Active lease.', $page['note']);
        $this->assertNull($page['refusal']);
        $this->assertSame(7, $page['suggestion']['unit']['id']);

        $capturer = $this->service()->page('equity', $id, self::UPLOADER);
        $this->assertStringContainsString('Only Accountant can match', $capturer['refusal'], 'the uploader is not a confirmer here');
        $this->assertNull($capturer['suggestion'], 'nothing to suggest to someone who cannot match');

        $this->assertNull($this->service()->page('equity', 99999, self::MATCHER));
    }

    /**
     * Matching and paying together: the line, the payment, the statement totals and the queued receipt.
     */
    public function test_a_line_is_matched_to_the_chosen_lease_and_paid_in_with_the_receipt_queued(): void
    {
        $first = $this->line('Unmatched', 2, 'R1', 7000);
        $second = $this->line('Ambiguous', 5, 'R2', 900);

        $result = $this->service()->matchByHand('equity', $first, 7, 101, self::MATCHER);

        $this->assertTrue($result['paid']);
        $this->assertSame('NAO AUTO SPARES', $result['tenant']);
        $this->assertSame('187G-8', $result['unit_code']);
        $this->assertSame('RCT-' . now()->format('Y') . '-00001', $result['receipt_number']);
        $this->assertSame('7,000.00', $result['amount']);
        $this->assertSame('5,000.00', $result['allocated']);
        $this->assertSame('2,000.00', $result['credit']);
        $this->assertSame($second, $result['next_line_id'], 'the next line waiting for a decision');

        $line = DB::table('cb_equity_statement_lines')->find($first);
        $this->assertSame('Paid', $line->match_status);
        $this->assertSame('187G-8', $line->unit_code, 'the line now shows the unit it was matched to');
        $this->assertSame([7, 101, 901, 5], [(int) $line->unit_id, (int) $line->lease_id, (int) $line->tenant_id, (int) $line->property_id]);
        $this->assertSame(self::MATCHER, (int) $line->matched_by);
        $this->assertNotNull($line->matched_at);
        $this->assertSame('Queued', $line->receipt_status);

        $payment = DB::table('sys_pm_tenants_payments')->find($line->payment_id);
        $this->assertSame([101, 901, 7, 5, 1], [(int) $payment->lease_id, (int) $payment->tenant_id, (int) $payment->unit_id, (int) $payment->property_id, (int) $payment->client_id]);
        $this->assertEquals(7000, $payment->amount);
        $this->assertSame('R1', $payment->reference);
        $this->assertSame(1, (int) $payment->extract_id);
        $this->assertSame(self::MATCHER, (int) $payment->created_by, 'created by the person who matched it, not the statement\'s confirmer');
        $this->assertStringContainsString('187G-8', $payment->narration);

        $upload = DB::table('cb_bank_statement_uploads')->find(1);
        $this->assertSame([1, 1, 1], [(int) $upload->paid_count, (int) $upload->matched_count, (int) $upload->unmatched_count], 'the statement totals follow the lines');
        $this->assertEquals(7000, $upload->paid_amount);

        Bus::assertDispatched(SendPaymentReceiptJob::class, fn ($job) => $job->bank === 'equity' && $job->lineId === $first && $job->paymentId === (int) $payment->id && $job->sentBy === self::MATCHER);
    }

    /**
     * An Ambiguous line and a lease that has ended can both be matched.
     */
    public function test_an_ambiguous_line_can_be_matched_and_so_can_a_tenant_who_has_left(): void
    {
        $id = $this->line('Ambiguous');

        $result = $this->service()->matchByHand('equity', $id, 7, 102, self::MATCHER);

        $this->assertTrue($result['paid']);
        $this->assertSame('CASA TENANT FORMER', $result['tenant']);
        $this->assertSame(102, (int) DB::table('sys_pm_tenants_payments')->value('lease_id'), 'the arrears of the tenant who left');
        $this->assertNull($result['next_line_id'], 'no other line is waiting');
    }

    /**
     * The tenant must be on the unit that was chosen; otherwise nothing is saved.
     */
    public function test_what_was_chosen_must_hang_together(): void
    {
        $id = $this->line();

        foreach ([[8, 101], [7, 999]] as [$unit, $lease]) {
            try {
                $this->service()->matchByHand('equity', $id, $unit, $lease, self::MATCHER);
                $this->fail('a tenant that is not on the chosen unit was accepted');
            } catch (BankStatementException $e) {
                $this->assertStringContainsString('not on the unit you chose', $e->getMessage());
            }
        }

        $this->assertSame('Unmatched', DB::table('cb_equity_statement_lines')->find($id)->match_status);
        $this->assertSame(0, DB::table('sys_pm_tenants_payments')->count());
        Bus::assertNotDispatched(SendPaymentReceiptJob::class);
    }

    /**
     * The rules hold in the service too, not just on the page, and a refusal changes nothing.
     */
    public function test_the_service_enforces_who_may_match_and_which_lines_and_changes_nothing_when_it_refuses(): void
    {
        $id = $this->line();
        $refused = function (int $user, string $message) use ($id) {
            try {
                $this->service()->matchByHand('equity', $id, 7, 101, $user);
                $this->fail('user ' . $user . ' matched the line');
            } catch (BankStatementException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        };

        $refused(self::OUTSIDER, 'Only Accountant can match');

        // The person who captured the statement may hold the confirming role, and still may not match its lines
        DB::table('cb_role_user')->insert(['role_id' => self::ROLE, 'user_id' => self::UPLOADER]);
        $refused(self::UPLOADER, 'You captured this statement');

        DB::table('cb_bank_statement_uploads')->where('id', 1)->update(['status' => 'Paying']);
        $refused(self::MATCHER, 'finished its run');
        DB::table('cb_bank_statement_uploads')->where('id', 1)->update(['status' => 'Completed']);

        DB::table('cb_equity_statement_lines')->where('id', $id)->update(['match_status' => 'Duplicate']);
        $refused(self::MATCHER, 'Only Unmatched or Ambiguous');

        $this->assertSame(0, DB::table('sys_pm_tenants_payments')->count());
        $this->assertNull(DB::table('cb_equity_statement_lines')->find($id)->matched_by);
        Bus::assertNotDispatched(SendPaymentReceiptJob::class);
    }

    /**
     * A second match of the same line is refused.
     */
    public function test_a_line_that_was_paid_cannot_be_matched_a_second_time(): void
    {
        $id = $this->line();
        $this->service()->matchByHand('equity', $id, 7, 101, self::MATCHER);

        $this->expectException(BankStatementException::class);
        $this->expectExceptionMessage('This line is paid');

        $this->service()->matchByHand('equity', $id, 7, 101, self::MATCHER);
    }

    /**
     * A failing payment rolls back the match, the receipt number and the totals.
     */
    public function test_a_payment_that_fails_leaves_the_line_exactly_as_it_was(): void
    {
        $id = $this->line();
        $this->allocation->shouldReceive('allocatePaymentById')->andThrow(new \RuntimeException('allocation exploded'))->once();

        try {
            $this->service()->matchByHand('equity', $id, 7, 101, self::MATCHER);
            $this->fail('the failure was swallowed');
        } catch (\RuntimeException $e) {
            $this->assertSame('allocation exploded', $e->getMessage());
        }

        $line = DB::table('cb_equity_statement_lines')->find($id);
        $this->assertSame('Unmatched', $line->match_status);
        $this->assertNull($line->lease_id);
        $this->assertNull($line->matched_by);
        $this->assertSame(0, DB::table('sys_pm_tenants_payments')->count());
        $this->assertSame(0, DB::table('cb_receipt_counters')->where('last_number', '>', 0)->count(), 'no receipt number was used up');
        $this->assertSame(0, (int) DB::table('cb_bank_statement_uploads')->find(1)->paid_count);
        Bus::assertNotDispatched(SendPaymentReceiptJob::class);
    }

    /**
     * A transaction already recorded as a payment is marked Duplicate and not paid again.
     */
    public function test_a_transaction_recorded_as_a_payment_in_the_meantime_is_marked_duplicate_and_not_paid_again(): void
    {
        $id = $this->line('Unmatched', 2, 'R1', 7000);
        DB::table('sys_pm_tenants_payments')->insert(['reference' => 'R1', 'amount' => 7000, 'lease_id' => 101]);

        $result = $this->service()->matchByHand('equity', $id, 7, 101, self::MATCHER);

        $this->assertFalse($result['paid']);
        $this->assertStringContainsString('already recorded as a payment', $result['message']);
        $this->assertSame('Duplicate', DB::table('cb_equity_statement_lines')->find($id)->match_status);
        $this->assertSame(1, DB::table('sys_pm_tenants_payments')->count(), 'still only the original payment');
        Bus::assertNotDispatched(SendPaymentReceiptJob::class);
    }

    /**
     * An unknown bank or line is refused with a clear message.
     */
    public function test_an_unknown_bank_or_line_is_refused(): void
    {
        try {
            $this->service()->matchByHand('kcb', 1, 7, 101, self::MATCHER);
            $this->fail('an unknown bank was accepted');
        } catch (BankStatementException $e) {
            $this->assertStringContainsString('Choose the bank', $e->getMessage());
        }

        $this->expectException(BankStatementException::class);
        $this->expectExceptionMessage('That line does not exist');

        $this->service()->matchByHand('equity', 99999, 7, 101, self::MATCHER);
    }
}

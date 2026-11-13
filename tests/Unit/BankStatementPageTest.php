<?php

namespace Tests\Unit;

use App\Http\Controllers\BankStatementController;
use App\Models\User;
use App\Services\BankStatement\BankStatementBanks;
use App\Services\BankStatement\CoopStatementParser;
use App\Services\BankStatement\EquityStatementParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * What the Bank Statements page is given: each bank's own list of statements (a busy bank cannot push another bank's
 * statements off its table), every row says which bank it is, the line counts come from the right bank's line table, the
 * page gets the columns each bank's file must have, and a user without the menu is refused.
 */
class BankStatementPageTest extends TestCase
{
    const USER = 10;
    const ROLE = 7;

    /**
     * Create the tables the page reads, give a director a user, and sign that user in.
     */
    protected function setUp(): void
    {
        parent::setUp();

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
        });
        Schema::create('sys_pm_tenants_payments', function ($table) {
            $table->increments('id');
            $table->string('receipt_number')->nullable();
        });
        Schema::create('cb_sub_modules', function ($table) {
            $table->increments('id');
            $table->string('sub_module_url')->nullable();
        });
        Schema::create('cb_sub_module_permissions', function ($table) {
            $table->integer('role_id');
            $table->integer('sub_module_id');
        });

        (include database_path('migrations/2026_11_13_120000_create_bank_statement_tables.php'))->up();
        (include database_path('migrations/2026_11_13_160000_add_manual_match_columns_to_bank_statement_lines.php'))->up();

        DB::table('josya_users')->insert(['id' => self::USER, 'name' => 'Amos']);
        DB::table('cb_roles')->insert(['id' => self::ROLE, 'name' => 'directors', 'display_name' => 'Directors']);
        DB::table('cb_role_user')->insert(['role_id' => self::ROLE, 'user_id' => self::USER]);

        $user = new User();
        $user->id = self::USER;
        $this->actingAs($user);
    }

    /**
     * Give the role of the signed-in user the Bank Statements menu.
     */
    private function grantMenu(): void {
        DB::table('cb_sub_modules')->insert(['id' => 1, 'sub_module_url' => '/tenant/bank-statements']);
        DB::table('cb_sub_module_permissions')->insert(['role_id' => self::ROLE, 'sub_module_id' => 1]);
    }

    /**
     * Insert uploads of one bank, oldest first.
     *
     * @param string $bank equity or coop
     * @param int $count how many
     * @param int $from the id of the first
     */
    private function uploads(string $bank, int $count, int $from = 1): void {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['id' => $from + $i, 'bank' => $bank, 'file_name' => "$bank-" . ($from + $i) . '.xlsx', 'file_path' => 'x', 'file_hash' => "$bank-" . ($from + $i), 'uploaded_by' => self::USER, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('cb_bank_statement_uploads')->insert($rows);
    }

    /**
     * The page's statements call, as the data it sends.
     */
    private function data(): array {
        return app(BankStatementController::class)->data()->getData(true);
    }

    /**
     * The list is the latest 100 of each bank, not the latest 100 of all, so a busy bank cannot hide another's statements.
     */
    public function test_a_banks_statements_are_not_pushed_off_its_table_by_another_bank_with_many_newer_ones(): void
    {
        $this->grantMenu();
        $this->uploads('coop', 1, 1);
        $this->uploads('equity', 105, 2);

        $rows = collect($this->data()['data']);

        $this->assertSame(100, $rows->where('bank_key', 'equity')->count(), 'the latest 100 of the busy bank');
        $this->assertSame(1, $rows->where('bank_key', 'coop')->count(), 'the older statement of the other bank is still listed');
        $this->assertSame(array_reverse(range(7, 106)), $rows->where('bank_key', 'equity')->pluck('id')->all(), 'newest first, the 5 oldest dropped');
    }

    /**
     * Each row carries its bank's key and label, and its line counts are read from that bank's own line table.
     */
    public function test_every_row_names_its_bank_and_counts_its_lines_from_that_banks_line_table(): void
    {
        $this->grantMenu();
        $this->uploads('equity', 1, 1);
        $this->uploads('coop', 1, 2);
        DB::table('cb_equity_statement_lines')->insert([
            ['upload_id' => 1, 'row_no' => 2, 'match_status' => 'Paid', 'created_at' => now(), 'updated_at' => now()],
            ['upload_id' => 1, 'row_no' => 3, 'match_status' => 'Unmatched', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('cb_coop_statement_lines')->insert([
            ['upload_id' => 2, 'row_no' => 2, 'match_status' => 'Pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $rows = collect($this->data()['data'])->keyBy('id');

        $this->assertSame('equity', $rows[1]['bank_key']);
        $this->assertSame('Equity Bank', $rows[1]['bank']);
        $this->assertSame(['Paid' => 1, 'Unmatched' => 1], $rows[1]['lines']);
        $this->assertSame('coop', $rows[2]['bank_key']);
        $this->assertSame('Co-operative Bank', $rows[2]['bank']);
        $this->assertSame(['Pending' => 1], $rows[2]['lines']);
    }

    /**
     * The lines call tells the page which lines this user can match by hand, or why not, and who matched one by hand.
     */
    public function test_lines_say_which_can_be_matched_by_this_user_and_who_matched_by_hand(): void
    {
        $this->grantMenu();
        DB::table('josya_users')->insert(['id' => 20, 'name' => 'Kenneth']);
        $this->uploads('equity', 1, 1);
        DB::table('cb_bank_statement_uploads')->where('id', 1)->update(['status' => 'Completed', 'uploaded_by' => 20]);
        foreach ([['Unmatched', 2], ['Paid', 3], ['Ambiguous', 4]] as [$status, $row]) {
            DB::table('cb_equity_statement_lines')->insert(['upload_id' => 1, 'row_no' => $row, 'match_status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('cb_equity_statement_lines')->where('row_no', 3)->update(['matched_by' => 20, 'matched_at' => '2026-11-13 17:05:00']);

        $lines = fn () => collect(app(BankStatementController::class)->lines(new Request(), 1)->getData(true)['data'])->keyBy('row_no');

        // Nobody holds the confirming role yet
        $this->assertFalse($lines()[2]['can_match']);
        $this->assertStringContainsString('Only nobody (not set) can match', $lines()[2]['match_hint']);
        $this->assertNull($lines()[3]['match_hint'], 'a Paid line has nothing to match, so no hint either');

        app(\App\Services\BankStatement\BankStatementConfirmationService::class)->setConfirmerRoles([self::ROLE], 1);

        $this->assertTrue($lines()[2]['can_match'], 'Unmatched, finished run, a confirmer who did not capture it');
        $this->assertTrue($lines()[4]['can_match'], 'Ambiguous too');
        $this->assertFalse($lines()[3]['can_match'], 'Paid');
        $this->assertNull($lines()[2]['match_hint']);
        $this->assertSame('Kenneth', $lines()[3]['matched_by']);
        $this->assertSame('2026-11-13 17:05', $lines()[3]['matched_at']);
        $this->assertNull($lines()[2]['matched_by']);
        $this->assertSame([1, 2, 3], $lines()->pluck('id')->all(), 'every line carries its own id for the Match link');

        // The signed-in user captured the statement: no Match, and the page says why
        DB::table('cb_bank_statement_uploads')->where('id', 1)->update(['uploaded_by' => self::USER]);
        $this->assertFalse($lines()[2]['can_match']);
        $this->assertStringContainsString('You captured this statement', $lines()[2]['match_hint']);

        // A run still going: lines cannot be matched yet
        DB::table('cb_bank_statement_uploads')->where('id', 1)->update(['uploaded_by' => 20, 'status' => 'Paying']);
        $this->assertFalse($lines()[2]['can_match']);
        $this->assertStringContainsString('finished its run', $lines()[2]['match_hint']);
    }

    /**
     * The page receives every bank's label and the headings its file must have, for the box above each table.
     */
    /**
     * The Confirm button is offered on statements someone else captured; on one the signed-in confirmer captured, the page
     * says another person has to confirm it. A user who cannot confirm at all gets neither.
     */
    public function test_confirm_is_only_offered_on_statements_someone_else_captured(): void
    {
        $this->grantMenu();
        DB::table('josya_users')->insert(['id' => 20, 'name' => 'Kenneth']);
        $this->uploads('equity', 2, 1);
        DB::table('cb_bank_statement_uploads')->where('id', 2)->update(['uploaded_by' => 20]);

        $row = fn (int $id) => collect($this->data()['data'])->firstWhere('id', $id);

        $this->assertFalse($row(1)['can_confirm'], 'not a confirmer yet');
        $this->assertFalse($row(1)['needs_other_confirmer'], 'not a confirmer yet');

        app(\App\Services\BankStatement\BankStatementConfirmationService::class)->setConfirmerRoles([self::ROLE], 1);

        $this->assertFalse($row(1)['can_confirm'], 'captured by the signed-in confirmer');
        $this->assertTrue($row(1)['needs_other_confirmer']);
        $this->assertTrue($row(2)['can_confirm'], 'captured by someone else');
        $this->assertFalse($row(2)['needs_other_confirmer']);
        $this->assertSame('Amos', $row(1)['uploader']);
        $this->assertSame('Kenneth', $row(2)['uploader']);
    }

    /**
     * The row names who captured the statement and, once confirmed, who confirmed it.
     */
    public function test_a_confirmed_row_names_who_captured_and_who_confirmed_it(): void
    {
        $this->grantMenu();
        DB::table('josya_users')->insert(['id' => 20, 'name' => 'Mark']);
        $this->uploads('equity', 1, 1);
        DB::table('cb_bank_statement_uploads')->where('id', 1)->update(['status' => 'Confirmed', 'confirmed_by' => 20, 'confirmed_at' => '2026-11-13 16:08:00']);

        $row = $this->data()['data'][0];

        $this->assertSame('Amos', $row['uploader']);
        $this->assertSame('Mark', $row['confirmer']);
        $this->assertSame('2026-11-13 16:08', $row['confirmed_at']);
    }

    public function test_the_page_is_given_each_banks_label_and_the_columns_its_file_must_have(): void
    {
        $this->grantMenu();

        $banks = app(BankStatementController::class)->index()->getData()['banks'];

        $this->assertSame(['equity', 'coop'], array_keys($banks));
        $this->assertSame('Equity Bank', $banks['equity']['label']);
        $this->assertSame(EquityStatementParser::REQUIRED, $banks['equity']['columns']);
        $this->assertSame('Co-operative Bank', $banks['coop']['label']);
        $this->assertSame(CoopStatementParser::REQUIRED, $banks['coop']['columns']);
    }

    /**
     * The headings shown to the user are the ones the parser actually enforces, so the two cannot drift apart.
     */
    public function test_the_columns_listed_for_a_bank_are_the_ones_its_parser_requires(): void
    {
        foreach (BankStatementBanks::all() as $bank => $definition) {
            $this->assertNotEmpty($definition['columns'], $bank);
            $this->assertSame($definition['parser']::REQUIRED, $definition['columns'], $bank);
        }
    }

    /**
     * Without the menu entry the statements call answers 403.
     */
    public function test_a_user_without_the_menu_is_refused_the_statements(): void
    {
        $response = app(BankStatementController::class)->data();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }
}

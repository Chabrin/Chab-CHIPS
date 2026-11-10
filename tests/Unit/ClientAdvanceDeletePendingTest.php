<?php

namespace Tests\Unit;

use App\Exceptions\ClientAccountException;
use App\Models\ClientAdvance;
use App\Services\ClientAdvanceService;
use App\Services\GlJournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientAdvanceDeletePendingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sys_pm_clients_advances_requisitions', function ($table) {
            $table->increments('id');
            $table->integer('current_status_id')->default(1);
            $table->string('reference_number')->nullable();
            $table->integer('previous_advance_id')->nullable();
            $table->string('loan_form_path')->nullable();
        });
        Schema::create('sys_pm_clients_advances_requisitions_89_repeat', function ($table) {
            $table->increments('id');
            $table->integer('parent_id');
            $table->date('payment_date')->nullable();
        });
        Schema::create('cb_advance_reschedule_history', function ($table) {
            $table->increments('id');
            $table->integer('original_advance_id')->nullable();
            $table->integer('from_advance_id')->nullable();
            $table->integer('to_advance_id')->nullable();
        });

        Storage::fake('local');
    }

    private function service(): ClientAdvanceService
    {
        return new ClientAdvanceService(new GlJournalPoster());
    }

    private function advance(int $status = 1, ?int $previous = null): ClientAdvance
    {
        $id = DB::table('sys_pm_clients_advances_requisitions')->insertGetId(['current_status_id' => $status, 'reference_number' => 'ADV-T-' . $status, 'previous_advance_id' => $previous]);

        return ClientAdvance::findOrFail($id);
    }

    public function test_deleting_a_pending_advance_removes_its_schedule_history_and_loan_form_only(): void
    {
        $advance = $this->advance();
        $other = $this->advance();
        DB::table('sys_pm_clients_advances_requisitions_89_repeat')->insert([['parent_id' => $advance->id], ['parent_id' => $advance->id], ['parent_id' => $other->id]]);
        DB::table('cb_advance_reschedule_history')->insert([
            ['original_advance_id' => $advance->id, 'from_advance_id' => $advance->id, 'to_advance_id' => $advance->id],
            ['original_advance_id' => 99, 'from_advance_id' => 99, 'to_advance_id' => $advance->id],
        ]);
        Storage::disk('local')->put('advance_loan_forms/' . $advance->id . '/form.pdf', 'x');

        $this->service()->deletePending($advance);

        $this->assertNull(ClientAdvance::find($advance->id));
        $this->assertNotNull(ClientAdvance::find($other->id));
        $this->assertSame(0, DB::table('sys_pm_clients_advances_requisitions_89_repeat')->where('parent_id', $advance->id)->count());
        $this->assertSame(1, DB::table('sys_pm_clients_advances_requisitions_89_repeat')->where('parent_id', $other->id)->count());
        $this->assertSame(1, DB::table('cb_advance_reschedule_history')->count(), "only the advance's own restructure row goes; another advance's history row stays");
        Storage::disk('local')->assertMissing('advance_loan_forms/' . $advance->id . '/form.pdf');
    }

    public function test_an_advance_that_is_no_longer_pending_is_refused_even_when_the_model_in_hand_is_stale(): void
    {
        $advance = $this->advance();
        // Posted by someone else after this model was loaded: the model still says Pending.
        DB::table('sys_pm_clients_advances_requisitions')->where('id', $advance->id)->update(['current_status_id' => 6]);
        DB::table('sys_pm_clients_advances_requisitions_89_repeat')->insert(['parent_id' => $advance->id]);
        $this->assertSame(1, (int) $advance->current_status_id);

        try {
            $this->service()->deletePending($advance);
            $this->fail('A Posted advance must not be deleted.');
        } catch (ClientAccountException $e) {
            $this->assertStringContainsString('no longer Pending', $e->getMessage());
        }

        $this->assertNotNull(ClientAdvance::find($advance->id));
        $this->assertSame(1, DB::table('sys_pm_clients_advances_requisitions_89_repeat')->where('parent_id', $advance->id)->count());
    }

    public function test_a_pending_replacement_of_an_earlier_advance_is_refused(): void
    {
        $old = $this->advance(8);
        $replacement = $this->advance(1, $old->id);

        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('replaces an earlier advance');

        try {
            $this->service()->deletePending($replacement);
        } finally {
            $this->assertNotNull(ClientAdvance::find($replacement->id));
        }
    }

    public function test_an_advance_that_is_already_gone_is_refused_not_a_crash(): void
    {
        $advance = $this->advance();
        DB::table('sys_pm_clients_advances_requisitions')->where('id', $advance->id)->delete();

        $this->expectException(ClientAccountException::class);
        $this->expectExceptionMessage('no longer exists');

        $this->service()->deletePending($advance);
    }
}

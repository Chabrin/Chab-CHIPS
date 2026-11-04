<?php

namespace Tests\Feature;

use App\Models\ClientAdvance;
use App\Services\ClientAdvanceService;
use App\Services\GlJournalPoster;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvanceLoanFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Schema::create('sys_pm_clients_advances_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->nullable();
            $table->string('loan_form_path')->nullable();
            $table->string('loan_form_name')->nullable();
            $table->integer('loan_form_uploaded_by')->nullable();
            $table->timestamp('loan_form_uploaded_at')->nullable();
        });
        Storage::fake('local');
    }

    /**
     * Attaching a form stores the file privately and records the name, user and time.
     */
    public function test_attaching_a_loan_form_stores_the_file_and_records_who_and_when(): void
    {
        $advance = ClientAdvance::create(['reference_number' => 'ADV-1']);
        (new ClientAdvanceService(new GlJournalPoster()))->attachLoanForm($advance, UploadedFile::fake()->create('form.pdf', 50, 'application/pdf'), 9);

        $advance->refresh();
        Storage::disk('local')->assertExists($advance->loan_form_path);
        $this->assertSame('form.pdf', $advance->loan_form_name);
        $this->assertSame(9, (int) $advance->loan_form_uploaded_by);
        $this->assertNotNull($advance->loan_form_uploaded_at);
    }

    /**
     * Replacing the form removes the previous file so no orphan is left on disk.
     */
    public function test_replacing_a_loan_form_deletes_the_old_file(): void
    {
        $service = new ClientAdvanceService(new GlJournalPoster());
        $advance = ClientAdvance::create(['reference_number' => 'ADV-2']);
        $service->attachLoanForm($advance, UploadedFile::fake()->create('first.pdf', 50, 'application/pdf'), 9);
        $first = $advance->refresh()->loan_form_path;

        $service->attachLoanForm($advance, UploadedFile::fake()->image('second.png'), 9);
        $advance->refresh();

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($advance->loan_form_path);
        $this->assertSame('second.png', $advance->loan_form_name);
    }
}

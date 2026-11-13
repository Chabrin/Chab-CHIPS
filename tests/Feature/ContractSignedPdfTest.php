<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffController;
use App\Models\Contract;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The signed contract (PDF) on the contract form. HR uploads it once, in Add Staff, and a contract made later uses
 * that file instead of asking for it again (or refusing it as a duplicate of the staff member's own copy).
 */
class ContractSignedPdfTest extends TestCase
{
    private const STAFF_ID = 5;
    private const OTHER_STAFF_ID = 99;
    private const TYPE_ID = 3;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        Schema::clearResolvedInstance('db.schema');

        Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        Storage::fake('local');

        Schema::create('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('photo')->nullable();
            $t->string('employment_type')->nullable();
        });
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        Schema::create('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->timestamp('date_time')->nullable();
            $t->integer('staff_id');
            $t->timestamp('date_start')->nullable();
            $t->timestamp('date_end')->nullable();
            $t->integer('current_status_id')->default(5);
            $t->integer('position_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->decimal('basic_salary', 15, 2)->default(0);
            $t->string('staff_number')->nullable();
        });
        (require database_path('migrations/2026_11_10_100200_add_period_and_probation_to_contracts.php'))->up();
        Schema::create('sys_hr_employee_contracts_279_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->integer('element_id');
            $t->integer('entry_type_id')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
        });
        // How often each row applies, what a part month does to it, and the company-wide items left out for a person
        (require database_path('migrations/2026_11_11_120000_add_terms_and_exclusions_for_contract_elements.php'))->up();
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) {
            $t->id();
            $t->string('element_name')->nullable();
        });
        Schema::create('contract_changes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->unsignedBigInteger('staff_id');
            $t->string('field', 30);
            $t->unsignedBigInteger('element_id')->nullable();
            $t->string('old_value', 50)->nullable();
            $t->string('new_value', 50)->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('document_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('contract_id')->nullable();
            $t->integer('document_type_id');
            $t->string('title')->nullable();
            $t->string('file_path');
            $t->string('file_name')->nullable();
            $t->integer('file_size')->nullable();
            $t->string('mime_type')->nullable();
            $t->integer('uploaded_by')->nullable();
            $t->timestamps();
        });

        DB::table('document_types')->insert(['id' => self::TYPE_ID, 'name' => 'Signed Employment Contract']);
        DB::table('sys_staff')->insert([['id' => self::STAFF_ID, 'staff_number' => '019'], ['id' => self::OTHER_STAFF_ID, 'staff_number' => '020']]);
    }

    private function pdf(string $name, string $text): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n" . $text);
    }

    /** A PDF already saved for a staff member, the way Add Staff stores it (not tied to any contract). */
    private function savedPdf(int $staffId, string $text, ?int $contractId = null): EmployeeDocument
    {
        $path = "employee_documents/{$staffId}/" . md5($text) . '.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n" . $text);

        return EmployeeDocument::create([
            'employee_id' => $staffId,
            'contract_id' => $contractId,
            'document_type_id' => self::TYPE_ID,
            'title' => 'Signed Employment Contract',
            'file_path' => $path,
            'file_name' => $text . '.pdf',
        ]);
    }

    private function fields(): array
    {
        return ['staff_id' => self::STAFF_ID, 'staff_positions' => 1, 'date_start' => '2026-01-01', 'date_end' => '2026-12-31', 'basic_salary' => 30000];
    }

    private function create(?UploadedFile $file = null): void
    {
        $request = Request::create('/hr/contracts/store', 'POST', $this->fields(), [], $file ? ['signed_contract' => $file] : []);
        app(StaffController::class)->contractStore($request);
    }

    private function update(int $contractId, ?UploadedFile $file = null): void
    {
        $request = Request::create("/hr/contracts/{$contractId}", 'PUT', $this->fields(), [], $file ? ['signed_contract' => $file] : []);
        app(StaffController::class)->contractUpdate($request, $contractId);
    }

    private function contractWithNoPdf(): int
    {
        return DB::table('sys_hr_employee_contracts')->insertGetId(['staff_id' => self::STAFF_ID, 'date_start' => '2026-01-01', 'date_end' => '2026-12-31', 'basic_salary' => 25000]);
    }

    public function test_a_contract_with_no_upload_uses_the_signed_contract_saved_with_the_staff_record(): void
    {
        $saved = $this->savedPdf(self::STAFF_ID, 'from add staff');

        $this->create();

        $contract = Contract::firstOrFail();
        $this->assertSame($contract->id, (int) $saved->fresh()->contract_id);
        $this->assertSame(1, EmployeeDocument::count(), 'no second copy is made');
        $this->assertStringContainsString('attached', session('success'));
    }

    public function test_picking_the_same_file_again_attaches_it_instead_of_refusing_it_as_a_duplicate(): void
    {
        $saved = $this->savedPdf(self::STAFF_ID, 'from add staff');

        $this->create($this->pdf('same-contract.pdf', 'from add staff'));

        $this->assertNull(session('errors')?->first('signed_contract'));
        $this->assertSame(1, Contract::count());
        $this->assertSame((int) Contract::first()->id, (int) $saved->fresh()->contract_id);
        $this->assertSame(1, EmployeeDocument::count(), 'the file is not stored twice');
        $this->assertTrue(Storage::disk('local')->exists($saved->file_path));
    }

    public function test_a_different_file_is_uploaded_and_the_one_from_add_staff_stays_on_the_staff_record(): void
    {
        $saved = $this->savedPdf(self::STAFF_ID, 'from add staff');

        $this->create($this->pdf('new.pdf', 'a newer scan'));

        $contractId = (int) Contract::first()->id;
        $this->assertSame(2, EmployeeDocument::count());
        $this->assertNull($saved->fresh()->contract_id);
        $this->assertSame('new.pdf', EmployeeDocument::where('contract_id', $contractId)->value('file_name'));
    }

    public function test_a_contract_with_nothing_on_file_and_no_upload_is_saved_without_a_pdf(): void
    {
        $this->create();

        $this->assertSame(1, Contract::count());
        $this->assertSame(0, EmployeeDocument::count());
        $this->assertStringNotContainsString('attached', session('success'));
    }

    public function test_another_staff_members_pdf_is_never_used(): void
    {
        $this->savedPdf(self::OTHER_STAFF_ID, 'belongs to someone else');

        $this->create();

        $this->assertNull(EmployeeDocument::first()->contract_id);
    }

    public function test_a_second_contract_does_not_take_the_pdf_the_first_one_already_has(): void
    {
        $this->savedPdf(self::STAFF_ID, 'from add staff');
        $this->create();
        $this->create();

        $this->assertSame(2, Contract::count());
        $first = Contract::orderBy('id')->first();
        $this->assertSame(1, EmployeeDocument::where('contract_id', $first->id)->count());
        $this->assertSame(1, EmployeeDocument::whereNotNull('contract_id')->count());
    }

    public function test_a_rejected_pdf_leaves_no_half_saved_contract_behind(): void
    {
        // Someone else already has this exact file, so the upload is refused after the contract rows would be written
        $this->savedPdf(self::OTHER_STAFF_ID, 'a file another employee already has');

        $this->create($this->pdf('copy.pdf', 'a file another employee already has'));

        $this->assertNotNull(session('errors')?->first('signed_contract'));
        $this->assertSame(0, Contract::count());
        $this->assertSame(0, DB::table('sys_hr_employee_contracts_279_repeat')->count());
        $this->assertSame(0, DB::table('contract_changes')->count());
    }

    public function test_saving_an_existing_contract_with_no_pdf_attaches_the_one_saved_with_the_staff_record(): void
    {
        $saved = $this->savedPdf(self::STAFF_ID, 'from add staff');
        $contractId = $this->contractWithNoPdf();

        $this->update($contractId);

        $this->assertSame($contractId, (int) $saved->fresh()->contract_id);
        $this->assertStringContainsString('attached', session('success'));
    }

    public function test_saving_a_contract_that_already_has_its_pdf_leaves_it_alone(): void
    {
        $contractId = $this->contractWithNoPdf();
        $own = $this->savedPdf(self::STAFF_ID, 'the contracts own pdf', $contractId);
        $spare = $this->savedPdf(self::STAFF_ID, 'a spare copy');

        $this->update($contractId);

        $this->assertSame($contractId, (int) $own->fresh()->contract_id);
        $this->assertNull($spare->fresh()->contract_id);
        $this->assertStringNotContainsString('attached', session('success'));
    }

    public function test_picking_the_staff_records_file_for_a_contract_that_has_another_pdf_swaps_them(): void
    {
        $contractId = $this->contractWithNoPdf();
        $current = $this->savedPdf(self::STAFF_ID, 'the contracts current pdf', $contractId);
        $staffCopy = $this->savedPdf(self::STAFF_ID, 'from add staff');

        $this->update($contractId, $this->pdf('again.pdf', 'from add staff'));

        $this->assertNull(EmployeeDocument::find($current->id));
        $this->assertFalse(Storage::disk('local')->exists($current->file_path));
        $this->assertSame($contractId, (int) $staffCopy->fresh()->contract_id);
        $this->assertSame(1, EmployeeDocument::count());
    }

    public function test_uploading_a_new_file_when_updating_replaces_the_contracts_pdf(): void
    {
        $contractId = $this->contractWithNoPdf();
        $old = $this->savedPdf(self::STAFF_ID, 'the old one', $contractId);

        $this->update($contractId, $this->pdf('new.pdf', 'the replacement'));

        $this->assertNull(EmployeeDocument::find($old->id));
        $this->assertFalse(Storage::disk('local')->exists($old->file_path));
        $this->assertSame('new.pdf', EmployeeDocument::where('contract_id', $contractId)->value('file_name'));
    }
}

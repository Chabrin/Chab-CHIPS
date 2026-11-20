<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffController;
use App\Models\Contract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentRemoval;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * One copy of the signed contract is kept. The contract forms show it (name, date, who uploaded it) with View and Remove and no
 * upload button; Remove takes it off the contract but never destroys it: the file stays and the removal is on record.
 */
class ContractDocumentRemovalTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const ME = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher(); // skip the audit-log observers; they need their own tables
        Storage::fake('local');

        Schema::create('josya_users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); });
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        DB::table('cb_roles')->insert([['id' => 1, 'name' => 'hr_manager'], ['id' => 2, 'name' => 'accountant'], ['id' => 3, 'name' => 'directors']]);
        Schema::create('document_types', function (Blueprint $t) { $t->id(); $t->string('name'); });
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
        (require database_path('migrations/2026_11_18_110000_create_employee_document_removals_table.php'))->up();
        DB::table('josya_users')->insert([['id' => self::ME, 'name' => 'Hannah Remover'], ['id' => 3, 'name' => 'Grace Uploader']]);
        DB::table('document_types')->insert(['id' => 1, 'name' => 'Signed Employment Contract']);

        $this->actAs(['read_staff', 'update_staff']);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    /** Sign in with only these permissions, holding this role (the HR Manager by default). */
    private function actAs(array $permissions, int $role = 1): void
    {
        DB::table('cb_role_user')->where('user_id', self::ME)->delete();
        DB::table('cb_role_user')->insert(['user_id' => self::ME, 'role_id' => $role]);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->shouldReceive('isAbleTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->id = self::ME;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function document(array $overrides = []): EmployeeDocument
    {
        $path = 'employee_documents/5/signed-' . uniqid() . '.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nsigned");

        return EmployeeDocument::create($overrides + [
            'employee_id' => 5, 'contract_id' => null, 'document_type_id' => 1, 'title' => 'Signed Employment Contract',
            'file_path' => $path, 'file_name' => 'signed.pdf', 'file_size' => 1, 'mime_type' => 'application/pdf', 'uploaded_by' => 3,
        ]);
    }

    // ---- removing ---------------------------------------------------------------------------------------------------------

    public function test_removing_a_document_keeps_the_file_and_records_who_and_when(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 10:30:00'));
        $document = $this->document();

        $this->deleteJson(route('documents.destroy', $document->id))->assertOk()->assertJson(['success' => true]);

        $this->assertNull(EmployeeDocument::find($document->id), 'It is off the contract and the staff member');
        Storage::disk('local')->assertExists($document->file_path);
        $removal = EmployeeDocumentRemoval::firstOrFail();
        $this->assertSame($document->id, (int) $removal->original_id);
        $this->assertSame($document->file_path, $removal->file_path);
        $this->assertSame('signed.pdf', $removal->file_name);
        $this->assertSame(5, (int) $removal->employee_id);
        $this->assertSame(3, (int) $removal->uploaded_by, 'Who uploaded it is kept');
        $this->assertSame(self::ME, (int) $removal->removed_by, 'and who removed it');
        $this->assertSame('2026-11-18 10:30:00', $removal->removed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Removed', $removal->reason);
    }

    public function test_a_plain_form_post_removes_it_too_and_goes_back_with_a_message(): void
    {
        $document = $this->document();

        $this->from('/hr/contracts')->delete(route('documents.destroy', $document->id))->assertRedirect('/hr/contracts')->assertSessionHas('success');

        $this->assertSame(1, EmployeeDocumentRemoval::count());
    }

    public function test_update_contracts_is_enough_but_read_only_access_is_not(): void
    {
        $document = $this->document();

        $this->actAs(['read_staff', 'read_contracts']);
        $this->deleteJson(route('documents.destroy', $document->id))->assertForbidden();

        $this->actAs(['update_staff', 'update_contracts'], 2); // an accountant, even with the permissions, is not HR
        $this->deleteJson(route('documents.destroy', $document->id))->assertForbidden();
        $this->assertNotNull(EmployeeDocument::find($document->id));

        $this->actAs(['update_contracts'], 3); // the Directors
        $this->deleteJson(route('documents.destroy', $document->id))->assertOk();
        $this->assertNull(EmployeeDocument::find($document->id));
    }

    public function test_a_removed_file_is_still_kept_when_the_history_table_has_not_been_added_yet(): void
    {
        Schema::drop('employee_document_removals');
        $document = $this->document();

        $this->deleteJson(route('documents.destroy', $document->id))->assertOk();

        Storage::disk('local')->assertExists($document->file_path);
        $this->assertNull(EmployeeDocument::find($document->id));
    }

    // ---- what the Add Contract form is given ------------------------------------------------------------------------------

    public function test_the_add_form_is_told_when_each_signed_contract_was_uploaded_and_by_whom(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00'));
        $document = $this->document(['employee_id' => 5, 'uploaded_by' => 3]);

        $onFile = app(StaffController::class)->contractsCreate()->getData()['staffSignedContracts'];

        $this->assertSame(
            ['id' => $document->id, 'name' => 'signed.pdf', 'uploaded' => '12 Nov 2026', 'by' => 'Grace Uploader'],
            $onFile[5]
        );
    }

    public function test_a_removed_contract_is_no_longer_offered_as_the_one_on_file(): void
    {
        $document = $this->document(['employee_id' => 5]);
        $this->assertArrayHasKey(5, app(StaffController::class)->contractsCreate()->getData()['staffSignedContracts']->all());

        $this->deleteJson(route('documents.destroy', $document->id))->assertOk();

        $this->assertArrayNotHasKey(5, app(StaffController::class)->contractsCreate()->getData()['staffSignedContracts']->all());
    }

    // ---- the pages --------------------------------------------------------------------------------------------------------

    private function editPage(?EmployeeDocument $signed, ?EmployeeDocument $staffCopy = null): string
    {
        $contract = new Contract();
        $contract->forceFill(['id' => 3, 'staff_id' => 5, 'basic_salary' => 50000]);
        $contract->setRelation('signedDocument', $signed);

        return view('staff.contracts.contracts_edit', [
            'contract' => $contract, 'visibleElements' => collect(), 'hiddenElements' => collect(), 'staffCopy' => $staffCopy, 'exclusions' => [],
        ])->render();
    }

    /** The signed contract box of a page: from its label to the end of the upload area. */
    private function box(string $page): string
    {
        $start = strpos($page, 'for="signed_contract"');
        $this->assertNotFalse($start, 'The signed contract box is missing');
        $end = strpos($page, 'id="signed_contract"', $start);

        return substr($page, $start, $end + 400 - $start);
    }

    public function test_the_add_form_starts_with_the_upload_button_and_a_hidden_card(): void
    {
        $page = view('staff.contracts.contracts_create', ['staffSignedContracts' => []])->render();
        $box = $this->box($page);

        $this->assertMatchesRegularExpression('/id="signed_contract_card" class="[^"]*d-none/', $box);
        $this->assertDoesNotMatchRegularExpression('/id="signed_contract_upload" class="[^"]*d-none/', $box);
        $this->assertStringContainsString('assets/js/signed-contract.js', $page);
        $this->assertStringNotContainsString('Upload a different file', $page, 'The old replace link is gone');
    }

    public function test_the_edit_form_shows_the_file_on_record_with_view_and_remove_and_no_upload_button(): void
    {
        $document = $this->document(['file_name' => 'signed contract.pdf', 'uploaded_by' => 3, 'contract_id' => 3]);
        $document->created_at = Carbon::parse('2026-11-12 09:00:00');

        $box = $this->box($this->editPage($document));

        $this->assertStringContainsString('signed contract.pdf', $box);
        $this->assertStringContainsString('Uploaded 12 Nov 2026 by Grace Uploader', $box);
        $this->assertStringContainsString('href="' . route('documents.view', $document->id) . '"', $box);
        $this->assertStringContainsString('id="signed_contract_remove"', $box);
        $this->assertDoesNotMatchRegularExpression('/id="signed_contract_card" class="[^"]*d-none/', $box, 'the card shows');
        $this->assertMatchesRegularExpression('/id="signed_contract_upload" class="[^"]*d-none/', $box, 'the upload button is hidden');
        $this->assertMatchesRegularExpression('/id="signed_contract"[^>]*\bdisabled\b/', $this->editPage($document), 'and sends nothing, so the contract keeps its file');
    }

    public function test_the_edit_form_with_nothing_on_record_shows_only_the_upload_button(): void
    {
        $page = $this->editPage(null);
        $box = $this->box($page);

        $this->assertMatchesRegularExpression('/id="signed_contract_card" class="[^"]*d-none/', $box);
        $this->assertDoesNotMatchRegularExpression('/id="signed_contract_upload" class="[^"]*d-none/', $box);
        $this->assertDoesNotMatchRegularExpression('/id="signed_contract"[^>]*\bdisabled\b/', $page);
    }

    public function test_a_staff_copy_not_yet_on_the_contract_is_shown_with_a_note_that_saving_attaches_it(): void
    {
        $copy = $this->document(['file_name' => 'from add staff.pdf']);

        $box = $this->box($this->editPage(null, $copy));

        $this->assertStringContainsString('from add staff.pdf', $box);
        $this->assertStringContainsString('It is attached to this contract when you save.', $box);
        $this->assertMatchesRegularExpression('/<div class="small text-muted\s*" id="signed_contract_note">/', $box);
    }

    public function test_someone_who_cannot_change_documents_sees_the_file_but_no_remove_button(): void
    {
        $document = $this->document(['contract_id' => 3]);
        $this->actAs(['read_staff', 'read_contracts']);

        $box = $this->box($this->editPage($document));

        $this->assertStringContainsString('signed.pdf', $box);
        $this->assertStringContainsString('id="signed_contract_view"', $box);
        $this->assertStringNotContainsString('id="signed_contract_remove"', $box);
    }
}

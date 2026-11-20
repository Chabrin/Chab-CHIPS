<?php

namespace Tests\Feature;

use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\StaffDocumentAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * Staff documents (ID and KRA PIN copies, signed contracts, certificates) can only be opened, downloaded or removed by the HR role
 * and the Directors, and only with the usual staff permission as well.
 */
class StaffDocumentAccessTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    private const ME = 7;
    private const HR = 1;
    private const ACCOUNTANT = 2;
    private const DIRECTORS = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout(); // the refusal page is drawn with the site layout
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
        Storage::fake('local');
        Schema::create('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->integer('contract_id')->nullable();
            $t->integer('document_type_id')->default(1);
            $t->string('title')->nullable();
            $t->string('file_path');
            $t->string('file_name')->nullable();
            $t->integer('file_size')->nullable();
            $t->string('mime_type')->nullable();
            $t->integer('uploaded_by')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_18_110000_create_employee_document_removals_table.php'))->up();
        DB::table('cb_roles')->insert([['id' => self::HR, 'name' => 'hr_manager'], ['id' => self::ACCOUNTANT, 'name' => 'accountant'], ['id' => self::DIRECTORS, 'name' => 'directors']]);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function actAs(array $permissions, int $role): User
    {
        DB::table('cb_role_user')->where('user_id', self::ME)->delete();
        DB::table('cb_role_user')->insert(['user_id' => self::ME, 'role_id' => $role]);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturnUsing(fn ($p) => in_array($p, $permissions, true));
        $user->id = self::ME;
        $this->actingAs($user);
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);

        return $user;
    }

    private function document(): EmployeeDocument
    {
        Storage::disk('local')->put('employee_documents/5/id.pdf', "%PDF-1.4\nid copy");

        return EmployeeDocument::create(['employee_id' => 5, 'title' => 'National ID / Passport', 'file_path' => 'employee_documents/5/id.pdf', 'file_name' => 'id.pdf', 'mime_type' => 'application/pdf']);
    }

    public function test_the_hr_role_and_the_directors_may_open_and_download_with_the_staff_permission(): void
    {
        $document = $this->document();

        foreach ([self::HR, self::DIRECTORS] as $role) {
            $this->actAs(['read_staff'], $role);
            $this->get(route('documents.view', $document->id))->assertOk();
            $this->get(route('documents.download', $document->id))->assertOk();
        }
    }

    public function test_any_other_role_is_refused_even_with_the_staff_permission(): void
    {
        $document = $this->document();
        $this->actAs(['read_staff', 'update_staff', 'read_contracts', 'update_contracts'], self::ACCOUNTANT);

        $this->get(route('documents.view', $document->id))->assertForbidden();
        $this->get(route('documents.download', $document->id))->assertForbidden();
        $this->deleteJson(route('documents.destroy', $document->id))->assertForbidden();
        $this->assertNotNull(EmployeeDocument::find($document->id), 'nothing was removed');
    }

    public function test_the_hr_role_without_the_staff_permission_is_refused_too(): void
    {
        $document = $this->document();
        $this->actAs(['read_contracts'], self::HR);

        $this->get(route('documents.view', $document->id))->assertForbidden();
        $this->get(route('documents.download', $document->id))->assertForbidden();
    }

    public function test_removing_needs_the_role_and_a_permission_to_change(): void
    {
        $document = $this->document();

        $this->actAs(['read_staff'], self::HR);
        $this->deleteJson(route('documents.destroy', $document->id))->assertForbidden();

        $this->actAs(['update_staff'], self::HR);
        $this->deleteJson(route('documents.destroy', $document->id))->assertOk();
    }

    public function test_the_allowed_roles_are_one_setting(): void
    {
        $this->assertEqualsCanonicalizing(['hr_manager', 'directors'], config('staff_documents.roles'));
        $document = $this->document();

        config(['staff_documents.roles' => ['accountant']]);
        $this->actAs(['read_staff'], self::ACCOUNTANT);
        $this->get(route('documents.view', $document->id))->assertOk();

        $this->actAs(['read_staff'], self::HR);
        $this->get(route('documents.view', $document->id))->assertForbidden();
    }

    public function test_an_empty_list_lets_nobody_in(): void
    {
        config(['staff_documents.roles' => []]);
        $user = $this->actAs(['read_staff'], self::HR);

        $this->assertFalse(app(StaffDocumentAccess::class)->roleAllowed($user));
        $this->assertFalse(app(StaffDocumentAccess::class)->allows($user));
    }

    public function test_role_names_are_matched_without_regard_to_capitals_and_nobody_signed_in_has_no_access(): void
    {
        config(['staff_documents.roles' => ['HR_Manager']]);
        $user = $this->actAs(['read_staff'], self::HR);

        $this->assertTrue(app(StaffDocumentAccess::class)->allows($user));
        $this->assertFalse(app(StaffDocumentAccess::class)->allows(null));
    }

    public function test_the_pages_that_link_to_documents_hide_the_links_from_everyone_else(): void
    {
        foreach (['staff/details.blade.php', 'staff/edit.blade.php', 'hr/reports/employee_documents.blade.php', 'staff/contracts/partials/signed-contract.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertStringContainsString('StaffDocumentAccess', $source, $view);
        }
    }
}

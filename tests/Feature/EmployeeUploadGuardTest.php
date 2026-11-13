<?php

namespace Tests\Feature;

use App\Services\EmployeeUploadGuard;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeUploadGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Explicitly isolate these tests from any inherited workstation DB settings.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_11_10_100000_create_employment_categories_table.php'))->up();
        Storage::fake('local');
        Storage::fake('public');
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('employee_id');
            $table->string('file_path');
        });
        Schema::create('sys_staff', function (Blueprint $table) {
            $table->id();
            $table->string('photo')->nullable();
        });
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function assertRejected(array $files, string $field): void
    {
        try {
            app(EmployeeUploadGuard::class)->validate($files);
            $this->fail('A duplicate file was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_duplicate_contents_in_different_fields_are_rejected_even_when_renamed(): void
    {
        $this->assertRejected([
            'documents.national_id' => $this->upload('id.pdf', 'same content'),
            'documents.kra_pin' => $this->upload('renamed.pdf', 'same content'),
        ], 'documents.kra_pin');
        $this->assertSame(0, DB::table('employee_documents')->count());
    }

    public function test_a_photo_cannot_also_be_selected_as_a_document(): void
    {
        $this->assertRejected([
            'photo' => $this->upload('photo.png', 'same image'),
            'documents.national_id' => $this->upload('id.png', 'same image'),
        ], 'documents.national_id');
    }

    public function test_an_existing_document_is_rejected_for_any_employee_or_upload_field(): void
    {
        Storage::disk('local')->put('employee_documents/12/id.png', 'existing document');
        DB::table('employee_documents')->insert(['employee_id' => 12, 'file_path' => 'employee_documents/12/id.png']);

        foreach (['photo', 'document_file', 'documents.national_id', 'signed_contract'] as $field) {
            $this->assertRejected([$field => $this->upload('renamed.png', 'existing document')], $field);
        }
        $this->assertSame(1, DB::table('employee_documents')->count());
    }

    public function test_existing_photos_are_checked_against_both_photos_and_documents(): void
    {
        Storage::disk('public')->put('staff_photos/existing.png', 'existing photo');
        DB::table('sys_staff')->insert(['id' => 12, 'photo' => 'staff_photos/existing.png']);

        foreach (['photo', 'document_file'] as $field) {
            $this->assertRejected([$field => $this->upload('renamed.png', 'existing photo')], $field);
        }
        $this->assertSame('existing photo', Storage::disk('public')->get('staff_photos/existing.png'));
    }

    public function test_different_contents_are_allowed_even_with_the_same_filename(): void
    {
        Storage::disk('local')->put('employee_documents/12/id.pdf', 'old content');
        DB::table('employee_documents')->insert(['employee_id' => 12, 'file_path' => 'employee_documents/12/id.pdf']);

        app(EmployeeUploadGuard::class)->validate([
            'documents.national_id' => $this->upload('id.pdf', 'new content'),
            'documents.kra_pin' => $this->upload('id.pdf', 'other content'),
        ]);
        $this->assertSame(1, DB::table('employee_documents')->count());
    }

    public function test_saving_without_new_uploads_does_not_query_files_or_database(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(EmployeeUploadGuard::class)->validate(['photo' => null, 'documents.cv' => null]);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_missing_legacy_files_do_not_block_a_new_upload(): void
    {
        DB::table('employee_documents')->insert(['employee_id' => 12, 'file_path' => 'missing.pdf']);
        DB::table('sys_staff')->insert(['id' => 12, 'photo' => 'missing.png']);
        app(EmployeeUploadGuard::class)->validate(['photo' => $this->upload('new.png', 'new image')]);
        $this->assertSame(1, DB::table('sys_staff')->count());
    }

    private function preflightUser(bool $allowed): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn($allowed);
        $this->actingAs($user);
        $this->withoutMiddleware();
    }

    public function test_live_check_returns_field_errors_without_saving_anything(): void
    {
        $this->preflightUser(true);
        Storage::disk('local')->put('employee_documents/12/id.pdf', 'existing document');
        DB::table('employee_documents')->insert(['employee_id' => 12, 'file_path' => 'employee_documents/12/id.pdf']);

        $this->postJson(route('hr.uploads.check-duplicates'), [
            'hashes' => ['documents.national_id' => hash('sha256', 'existing document')],
        ])->assertUnprocessable()->assertJsonValidationErrors('documents.national_id');
        $this->assertSame(1, DB::table('employee_documents')->count());
        $this->assertSame(['employee_documents/12/id.pdf'], Storage::disk('local')->allFiles());
    }

    public function test_live_check_accepts_new_contents(): void
    {
        $this->preflightUser(true);
        $this->postJson(route('hr.uploads.check-duplicates'), [
            'hashes' => ['photo' => hash('sha256', 'new image')],
        ])->assertOk()->assertJson(['valid' => true]);
    }

    public function test_live_check_requires_upload_permission(): void
    {
        $this->preflightUser(false);
        $this->postJson(route('hr.uploads.check-duplicates'), [
            'hashes' => ['photo' => hash('sha256', 'new image')],
        ])->assertForbidden();
    }

    public function test_live_check_rejects_invalid_fingerprints(): void
    {
        $this->preflightUser(true);
        $this->postJson(route('hr.uploads.check-duplicates'), [
            'hashes' => ['photo' => 'not-a-sha256'],
        ])->assertUnprocessable()->assertJsonValidationErrors('hashes.photo');
    }

    public function test_preflight_explains_another_employee_conflict_without_exposing_identity(): void
    {
        $this->preflightUser(true);
        Storage::disk('local')->put('employee_documents/12/id.pdf', 'existing document');
        DB::table('employee_documents')->insert(['employee_id' => 12, 'file_path' => 'employee_documents/12/id.pdf']);
        $response = $this->postJson(route('hr.uploads.check-duplicates'), [
            'hashes' => ['documents.national_id' => hash('sha256', 'existing document')],
        ])->assertUnprocessable();
        $message = $response->json('errors')['documents.national_id'][0];
        $this->assertStringContainsString('National ID / Passport copy', $message);
        $this->assertStringContainsString('another employee', $message);
        $this->assertStringContainsString('renaming', $message);
        $this->assertStringNotContainsString('12', $message);
    }

    public function test_edit_preflight_distinguishes_a_file_already_saved_for_this_employee(): void
    {
        $this->preflightUser(true);
        Storage::disk('public')->put('staff_photos/existing.png', 'existing photo');
        DB::table('sys_staff')->insert(['id' => 12, 'photo' => 'staff_photos/existing.png']);
        $response = $this->postJson(route('hr.uploads.check-duplicates'), [
            'staff_id' => 12, 'hashes' => ['photo' => hash('sha256', 'existing photo')],
        ])->assertUnprocessable();
        $this->assertStringContainsString('this employee', $response->json('errors.photo.0'));
    }

    private function staffFormData(): array
    {
        Schema::table('sys_staff', function (Blueprint $table) {
            $table->string('email_address')->nullable();
            $table->string('national_id')->nullable();
        });
        Schema::create('cb_roles', fn (Blueprint $table) => $table->id());
        DB::table('cb_roles')->insert(['id' => 1]);
        return [
            'full_names' => 'Test Staff', 'employment_type' => 'Permanent', 'email_address' => 'test.staff@chabrinagencies.co.ke',
            'national_id' => 'TEST123', 'gender' => 'Male', 'birth_date' => '2000-01-01',
            'bank_name' => 1, 'account_name' => 'Test Staff', 'account_number' => '123',
            'pin_number' => 'TESTPIN', 'nhif_number' => 'TESTSHA', 'nssf_number' => 'TESTNSSF',
            'marital' => 'Single', 'citizenship' => 'Kenya', 'disability' => 'No',
            'login_email' => 'test.login@chabrinagencies.co.ke', 'role_id' => 1,
            'documents' => [
                'national_id' => $this->upload('id.pdf', "%PDF-1.4\nTest ID document"),
                'kra_pin' => $this->upload('pin.pdf', "%PDF-1.4\nDifferent certificate"),
            ],
        ];
    }

    public function test_staff_save_returns_field_validation_as_json_without_a_reload(): void
    {
        $this->preflightUser(true);
        $form = $this->staffFormData();
        Storage::disk('local')->put('employee_documents/12/id.pdf', "%PDF-1.4\nTest ID document");
        DB::table('employee_documents')->insert(['employee_id' => 12, 'file_path' => 'employee_documents/12/id.pdf']);
        $response = $this->post(route('staff.store'), $form, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('documents.national_id');
        $this->assertStringContainsString('another employee', $response->json('errors')['documents.national_id'][0]);
        $this->assertFalse($response->isRedirect());
        $this->assertSame(0, DB::table('sys_staff')->count());
        $this->assertSame(1, DB::table('employee_documents')->count());
    }

    public function test_php_rejected_upload_has_a_clear_limit_message_in_the_json_response(): void
    {
        $this->preflightUser(true);
        $form = $this->staffFormData();
        $original = $form['documents']['national_id'];
        $form['documents']['national_id'] = new UploadedFile($original->getPathname(), 'id.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);
        $response = $this->post(route('staff.store'), $form, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('documents.national_id');
        $message = $response->json('errors')['documents.national_id'][0];
        $this->assertStringContainsString('upload limit', $message);
        $this->assertStringContainsString('National ID', $message);
        $this->assertFalse($response->isRedirect());
    }

    public function test_upload_partial_renders_the_effective_server_limits_and_ajax_save_handler(): void
    {
        $this->view('staff.partials.upload-duplicate-check')
            ->assertSee('const uploadLimits =', false)
            ->assertSee('EmployeeStaffForm', false)
            ->assertSee('new FormData(form)', false);
        $limits = \App\Support\EmployeeUploadLimits::current();
        $this->assertGreaterThan(0, $limits['file']);
        $this->assertArrayHasKey('post', $limits);
    }
}

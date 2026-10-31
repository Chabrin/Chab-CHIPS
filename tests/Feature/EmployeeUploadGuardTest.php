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
}

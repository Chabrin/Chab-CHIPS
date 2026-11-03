<?php

namespace Tests\Feature;

use App\Mail\StaffOnboardingInvitationMail;
use App\Models\StaffOnboardingInvitation;
use App\Models\User;
use App\Services\EmployeeUploadGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffOnboardingInvitationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php'))->up();
        (require database_path('migrations/2026_11_03_000001_make_onboarding_employment_type_nullable.php'))->up();
        Schema::create('sys_set_banks', function (Blueprint $table) { $table->id(); $table->string('bank_name'); });
        Schema::create('sys_staff', function (Blueprint $table) {
            $table->id();
            $table->string('photo')->nullable();
            $table->string('national_id')->nullable();
            $table->string('email_address')->nullable();
            $table->string('personal_email')->nullable();
        });
        (require database_path('migrations/2026_11_03_000002_add_signature_to_sys_staff.php'))->up();
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('employee_id');
            $table->string('file_path');
        });
        Storage::fake('local');
        Storage::fake('public');
    }

    private function actingAsHr(bool $allowed = true): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn($allowed);
        $user->id = 7;
        $this->actingAs($user);
        // Keep route model binding; skip only the DB-backed session and path permission checks
        $this->withoutMiddleware([\App\Http\Middleware\AccessChecker::class, \App\Http\Middleware\OneLogin::class]);
    }

    private function invitation(string $status, ?array $submission = null): StaffOnboardingInvitation
    {
        return StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test',
            'token_hash' => hash('sha256', str_repeat($status[0], 64)),
            'invited_by' => 7,
            'status' => $status,
            'submission' => $submission,
            'expires_at' => now()->addDay(),
        ]);
    }

    /** A submission with a stored ID copy and photo, as the public form saves it. */
    private function submittedWithFiles(string $status = 'submitted'): StaffOnboardingInvitation
    {
        Storage::disk('local')->put('staff_onboarding/1/id.pdf', "%PDF-1.4\nonboarding id");
        Storage::disk('local')->put('staff_onboarding/1/photo.png', 'onboarding photo');
        return $this->invitation($status, [
            'full_names' => 'Example Staff', 'national_id' => '12345678', 'bank_name' => 1,
            'staff_photo_path' => 'staff_onboarding/1/photo.png',
            'onboarding_documents' => [[
                'type_name' => 'National ID / Passport', 'path' => 'staff_onboarding/1/id.pdf',
                'name' => 'my-id.pdf', 'size' => 1, 'mime' => 'application/pdf',
            ]],
        ]);
    }

    public function test_pending_personal_details_are_encrypted_at_rest(): void
    {
        $invitation = $this->invitation('submitted', ['full_names' => 'Example Staff', 'national_id' => '12345678']);

        $this->assertSame(['full_names' => 'Example Staff', 'national_id' => '12345678'], $invitation->fresh()->submission);
        $this->assertStringNotContainsString('Example Staff', DB::table('staff_onboarding_invitations')->value('submission'));
    }

    public function test_public_onboarding_link_requires_a_valid_issued_token_but_not_a_login(): void
    {
        $this->invitation('sent');

        $this->get(route('staff.onboarding.complete', str_repeat('s', 64)))
            ->assertOk()->assertSee('Staff onboarding')->assertSee('Next of kin names')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('staff.onboarding.complete', str_repeat('c', 64)))->assertNotFound();
    }

    public function test_expired_or_consumed_tokens_cannot_open_the_form(): void
    {
        $this->invitation('submitted');

        $this->get(route('staff.onboarding.complete', str_repeat('s', 64)))->assertGone();
    }

    public function test_an_invitation_needs_only_the_recipient_email(): void
    {
        $this->actingAsHr();
        Mail::fake();

        $this->post(route('staff.onboarding.send'), [
            'recipient_email' => 'New.Staff@example.test', 'recipient_email_confirmation' => 'New.Staff@example.test',
        ])->assertRedirect(route('staff.onboarding.index'));

        $invitation = StaffOnboardingInvitation::sole();
        $this->assertSame('new.staff@example.test', $invitation->recipient_email);
        $this->assertNull($invitation->employment_type);
        $this->assertNull($invitation->role_id);
        Mail::assertSent(StaffOnboardingInvitationMail::class);
    }

    /** The minimum valid public form, plus any extra fields. */
    private function publicForm(array $extra = []): array
    {
        DB::table('sys_set_banks')->insertOrIgnore(['id' => 1, 'bank_name' => 'Test Bank']);
        return $extra + [
            'full_names' => 'Example Staff', 'gender' => 'Female', 'birth_date' => '1995-02-03',
            'national_id' => '12345678', 'marital' => 'Single', 'citizenship' => 'RESIDENT', 'disability' => 'NO',
            'mobile_number' => '0700000000', 'personal_email' => 'me@example.test',
            'bank_name' => 1, 'account_name' => 'Example Staff', 'account_number' => '001', 'pin_number' => 'A001',
            'nhif_number' => 'SHA1', 'nssf_number' => 'NSSF1',
            'documents' => [
                'national_id' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4
id"),
                'kra_pin' => UploadedFile::fake()->createWithContent('pin.pdf', "%PDF-1.4
pin"),
            ],
        ];
    }

    public function test_a_drawn_signature_is_saved_with_the_submission(): void
    {
        $this->invitation('sent');
        $png = UploadedFile::fake()->image('signature.png', 120, 40)->get();

        $this->post(route('staff.onboarding.submit', str_repeat('s', 64)), $this->publicForm([
            'signature_data' => 'data:image/png;base64,' . base64_encode($png),
        ]))->assertOk()->assertSee('Thank you');

        $invitation = StaffOnboardingInvitation::sole();
        $this->assertSame($png, Storage::disk('local')->get($invitation->submission['signature_path']));
        $this->assertArrayHasKey('signature', $invitation->submittedFiles());
    }

    public function test_an_uploaded_signature_image_is_saved_with_the_submission(): void
    {
        $this->invitation('sent');

        $this->post(route('staff.onboarding.submit', str_repeat('s', 64)), $this->publicForm([
            'signature_file' => UploadedFile::fake()->image('my-signature.jpg', 300, 100),
        ]))->assertOk();

        $this->assertTrue(Storage::disk('local')->exists(StaffOnboardingInvitation::sole()->submission['signature_path']));
    }

    public function test_a_drawn_signature_that_is_not_a_png_is_rejected_and_nothing_is_kept(): void
    {
        $this->invitation('sent');

        $this->post(route('staff.onboarding.submit', str_repeat('s', 64)), $this->publicForm([
            'signature_data' => 'data:image/png;base64,' . base64_encode('<script>not an image</script>'),
        ]))->assertSessionHasErrors('signature_data');

        $this->assertSame('sent', StaffOnboardingInvitation::sole()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('staff_onboarding'));
    }

    public function test_the_staff_signature_is_only_shown_to_users_who_can_read_staff(): void
    {
        Storage::disk('local')->put('staff_signatures/5/signature.png', 'png bytes');
        DB::table('sys_staff')->insert(['id' => 5, 'signature' => 'staff_signatures/5/signature.png']);

        $this->actingAsHr();
        $this->get(route('staff.signature', 5))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        // The app's 403 page needs role tables this test doesn't build, so check the refusal itself
        $this->actingAsHr(false);
        $this->withoutExceptionHandling();
        try {
            $this->get(route('staff.signature', 5));
            $this->fail('The signature was shown without read_staff.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_the_public_form_collects_contact_details_for_every_staff_tab(): void
    {
        $this->invitation('sent');
        DB::table('sys_set_banks')->insert(['id' => 1, 'bank_name' => 'Test Bank']);

        $this->post(route('staff.onboarding.submit', str_repeat('s', 64)), [
            'full_names' => 'Example Staff', 'gender' => 'Female', 'birth_date' => '1995-02-03', 'birth_place' => 'Nakuru',
            'national_id' => '12345678', 'marital' => 'Single', 'citizenship' => 'RESIDENT', 'disability' => 'NO',
            'mobile_number' => '0700000000', 'personal_email' => 'me@example.test', 'district' => 'Nakuru East',
            'location' => 'Milimani', 'sub_location' => 'Section 58', 'kins_names' => 'Jane Example',
            'bank_name' => 1, 'account_name' => 'Example Staff', 'account_number' => '001', 'pin_number' => 'A001',
            'nhif_number' => 'SHA1', 'nssf_number' => 'NSSF1',
            'documents' => [
                'national_id' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nid"),
                'kra_pin' => UploadedFile::fake()->createWithContent('pin.pdf', "%PDF-1.4\npin"),
            ],
            'staff_photo' => UploadedFile::fake()->image('me.png', 200, 200),
        ])->assertOk()->assertSee('Thank you');

        $submission = StaffOnboardingInvitation::sole()->submission;
        $this->assertSame('Nakuru', $submission['birth_place']);
        $this->assertSame('Section 58', $submission['sub_location']);
        $this->assertSame('Jane Example', $submission['kins_names']);
        $this->assertArrayNotHasKey('documents', $submission);
        $this->assertCount(2, $submission['onboarding_documents']);
        $this->assertTrue(Storage::disk('local')->exists($submission['staff_photo_path']));
        $this->assertArrayHasKey('photo', StaffOnboardingInvitation::sole()->submittedFiles());
    }

    public function test_hr_can_preview_the_submitted_staff_photo(): void
    {
        $this->actingAsHr();
        $invitation = $this->submittedWithFiles();

        $this->get(route('staff.onboarding.document', [$invitation, hash('sha256', 'staff_onboarding/1/photo.png')]))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_approving_a_submission_continues_to_the_add_staff_form(): void
    {
        $this->actingAsHr();
        $invitation = $this->submittedWithFiles();

        $this->post(route('staff.onboarding.approve', $invitation))
            ->assertRedirect(route('staff.create', ['onboarding' => $invitation->id]));

        $invitation->refresh();
        $this->assertSame('reviewed', $invitation->status);
        $this->assertSame('Example Staff', $invitation->submission['full_names']);
    }

    public function test_add_staff_does_not_ask_again_for_documents_received_through_onboarding(): void
    {
        $this->actingAsHr();
        $invitation = $this->submittedWithFiles('reviewed');

        $response = $this->post(route('staff.store'), ['onboarding_invitation_id' => $invitation->id], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $errors = $response->json('errors');
        $this->assertArrayHasKey('full_names', $errors);
        $this->assertArrayNotHasKey('documents.national_id', $errors);
        $this->assertArrayHasKey('documents.kra_pin', $errors);
    }

    public function test_add_staff_refuses_a_submission_that_was_not_approved(): void
    {
        $this->actingAsHr();
        $invitation = $this->submittedWithFiles('submitted');

        $this->post(route('staff.store'), ['onboarding_invitation_id' => $invitation->id], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('onboarding_invitation_id');
    }

    public function test_a_submissions_own_files_only_count_as_duplicates_for_other_records(): void
    {
        $invitation = $this->submittedWithFiles('reviewed');
        $hash = hash('sha256', "%PDF-1.4\nonboarding id");

        app(EmployeeUploadGuard::class)->validateHashes(['documents.national_id' => $hash], null, $invitation->id);

        $this->expectException(ValidationException::class);
        app(EmployeeUploadGuard::class)->validateHashes(['documents.national_id' => $hash]);
    }

    public function test_rejecting_a_submission_removes_its_documents_and_photo(): void
    {
        $this->actingAsHr();
        $invitation = $this->submittedWithFiles();

        $this->delete(route('staff.onboarding.revoke', $invitation))->assertRedirect(route('staff.onboarding.index'));

        $this->assertSame('revoked', $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->submission);
        $this->assertSame([], Storage::disk('local')->allFiles('staff_onboarding'));
    }
}

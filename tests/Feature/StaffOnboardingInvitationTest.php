<?php

namespace Tests\Feature;

use App\Models\StaffOnboardingInvitation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffOnboardingInvitationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.prefix' => '', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_30_120000_create_staff_onboarding_invitations_table.php'))->up();
        Schema::create('sys_set_banks', function (Blueprint $table) { $table->id(); $table->string('bank_name'); });
    }

    public function test_pending_personal_details_are_encrypted_at_rest(): void
    {
        $invitation = StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test',
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'employment_type' => 'Intern',
            'invited_by' => 7,
            'status' => 'submitted',
            'submission' => ['full_names' => 'Example Staff', 'national_id' => '12345678'],
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame(['full_names' => 'Example Staff', 'national_id' => '12345678'], $invitation->fresh()->submission);
        $this->assertStringNotContainsString('Example Staff', DB::table('staff_onboarding_invitations')->value('submission'));
    }

    public function test_public_onboarding_link_requires_a_valid_issued_token_but_not_a_login(): void
    {
        $token = str_repeat('b', 64);
        StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test',
            'token_hash' => hash('sha256', $token),
            'employment_type' => 'Casual',
            'invited_by' => 7,
            'status' => 'sent',
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('staff.onboarding.complete', $token))
            ->assertOk()->assertSee('Staff onboarding')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('staff.onboarding.complete', str_repeat('c', 64)))->assertNotFound();
    }

    public function test_expired_or_consumed_tokens_cannot_open_the_form(): void
    {
        $token = str_repeat('d', 64);
        StaffOnboardingInvitation::create([
            'recipient_email' => 'new.staff@example.test',
            'token_hash' => hash('sha256', $token),
            'employment_type' => 'Casual',
            'invited_by' => 7,
            'status' => 'submitted',
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('staff.onboarding.complete', $token))->assertGone();
    }
}

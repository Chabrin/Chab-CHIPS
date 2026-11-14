<?php

namespace Tests\Feature;

use App\Events\StaffMessagingUpdated;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Models\User;
use App\Services\StaffPasswordPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffMessagingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // These fixtures are isolated from the workstation's property database.
        config(['database.default' => 'messaging_test', 'database.connections.messaging_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('messaging_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->mock(StaffPasswordPolicy::class)->shouldReceive('requiresChange')->andReturn(false);
        Event::fake([StaffMessagingUpdated::class]);

        Schema::create('josya_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('block')->default(false);
            $table->unsignedBigInteger('staff_id')->nullable();
        });
        Schema::create('sys_staff', function (Blueprint $table) {
            $table->id();
            $table->integer('current_status_id');
        });
        Schema::create('sys_user_staff_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('staff_id');
        });
        Schema::create('cb_role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('user_type')->default(User::class);
        });
        Schema::create('cb_roles', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
        });
        DB::table('cb_roles')->insert([
            ['id' => 1, 'display_name' => 'Staff'], ['id' => 99, 'display_name' => 'Director'],
        ]);
        Schema::create('cb_sub_modules', function (Blueprint $table) {
            $table->id();
            $table->string('sub_module_url');
        });
        (require base_path('database/migrations/2026_11_06_141825_create_staff_messaging_tables.php'))->up();
        (require base_path('database/migrations/2026_11_06_160000_add_client_token_to_staff_messages.php'))->up();
        (require base_path('database/migrations/2026_11_11_170000_create_staff_message_attachments_table.php'))->up();

        foreach ([1 => 1, 2 => 4, 3 => 1, 4 => 2, 5 => 1, 7 => 1, 8 => 1] as $id => $status) {
            DB::table('sys_staff')->insert(['id' => $id, 'current_status_id' => $status]);
        }
        foreach (range(1, 8) as $id) {
            DB::table('josya_users')->insert([
                'id' => $id, 'name' => 'Staff ' . $id, 'block' => $id === 5,
                'staff_id' => in_array($id, [7, 8]) ? $id : null,
            ]);
            DB::table('cb_role_user')->insert(['user_id' => $id, 'role_id' => $id === 3 ? 99 : 1]);
        }
        foreach (range(1, 5) as $id) {
            DB::table('sys_user_staff_links')->insert(['user_id' => $id, 'staff_id' => $id]);
        }
        DB::table('sys_user_staff_links')->insert(['user_id' => 8, 'staff_id' => 4]);
        $this->actingAs(User::findOrFail(1));
    }

    private function conversation(int $first = 1, int $second = 2): StaffConversation
    {
        return StaffConversation::create(['first_user_id' => $first, 'second_user_id' => $second]);
    }

    public function test_attachments_are_private_and_a_retry_does_not_store_them_twice(): void
    {
        $disk = Storage::fake('staff-attachments');
        $conversation = $this->conversation();
        $url = "/staff-messaging/conversations/{$conversation->id}/messages";
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ3sAAAAASUVORK5CYII=');
        $token = (string) Str::uuid();
        $makeUpload = function ($name, $contents) {
            $fake = UploadedFile::fake()->createWithContent($name, $contents);
            // Use an actual UploadedFile for MIME detection rather than the fake's filename-based MIME.
            return [$fake, new UploadedFile($fake->getPathname(), $name, null, UPLOAD_ERR_OK, true)];
        };
        $send = function ($contents) use ($makeUpload, $url, $token, $png) {
            [$pdfTemp, $pdfFile] = $makeUpload('note.pdf', $contents);
            [$pngTemp, $pngFile] = $makeUpload('photo.png', $png);

            return $this->post($url, ['client_token' => $token, 'attachments' => [$pdfFile, $pngFile]], ['Accept' => 'application/json']);
        };

        $result = $send($pdf)->assertCreated()
            ->assertJsonPath('message.body', '')->assertJsonPath('message.attachment_count', 2)
            ->assertJsonMissingPath('message.attachments.0.storage_path')->json('message');
        $send($pdf)->assertOk()->assertJsonPath('message.id', $result['id']);
        $send($pdf."\nchanged")->assertConflict();
        $this->assertCount(2, $disk->allFiles());
        $this->assertSame(1, StaffMessage::count());

        $this->actingAs(User::findOrFail(2));
        $this->getJson($url)->assertOk()->assertJsonPath('messages.0.attachments.1.name', 'photo.png');
        $this->getJson('/staff-messaging/conversations')->assertOk()->assertJsonPath('conversations.0.latest_message.attachment_count', 2);
        $download = $this->get($result['attachments'][0]['url'])->assertOk()->assertDownload('note.pdf');
        $this->assertSame($pdf, $download->streamedContent());
        $this->actingAs(User::findOrFail(3));
        $this->getJson($result['attachments'][0]['url'])->assertNotFound();
    }

    public function test_disguised_file_is_rejected_without_creating_a_message(): void
    {
        $disk = Storage::fake('staff-attachments');
        $conversation = $this->conversation();
        $temp = UploadedFile::fake()->createWithContent('fake.png', '<html>Not an image</html>');
        $file = new UploadedFile($temp->getPathname(), 'fake.png', 'image/png', UPLOAD_ERR_OK, true);
        $this->post("/staff-messaging/conversations/{$conversation->id}/messages", [
            'client_token' => (string) Str::uuid(),
            'attachments' => [$file],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->assertSame([], $disk->allFiles());
        $this->assertSame(0, StaffMessage::count());
    }

    private function message(StaffConversation $conversation, int $sender, string $body = 'Hello'): StaffMessage
    {
        return StaffMessage::create([
            'conversation_id' => $conversation->id, 'sender_id' => $sender,
            'body' => $body, 'client_token' => (string) Str::uuid(),
        ]);
    }

    public function test_starting_from_either_side_reuses_one_conversation(): void
    {
        $first = $this->postJson('/staff-messaging/conversations', ['user_id' => 2])
            ->assertCreated()->json('conversation.id');
        $this->actingAs(User::findOrFail(2));
        $this->postJson('/staff-messaging/conversations', ['user_id' => 1])
            ->assertOk()->assertJsonPath('conversation.id', $first);
        $this->assertSame(1, StaffConversation::count());
        $this->postJson('/staff-messaging/conversations', ['user_id' => 2])->assertUnprocessable();
    }

    public function test_directory_and_eligibility_honor_staff_link_precedence_and_legacy_accounts(): void
    {
        $this->getJson('/staff-messaging/directory')->assertOk()->assertJsonPath('users', [
            ['id' => 2, 'name' => 'Staff 2', 'role' => 'Staff'],
            ['id' => 3, 'name' => 'Staff 3', 'role' => 'Director'],
            ['id' => 7, 'name' => 'Staff 7', 'role' => 'Staff'],
        ]);
        foreach ([4, 5, 6, 8] as $id) {
            $this->postJson('/staff-messaging/conversations', ['user_id' => $id])->assertUnprocessable();
        }
        $this->actingAs(User::findOrFail(5));
        $this->getJson('/staff-messaging/directory')->assertForbidden();
        $this->postJson('/staff-messaging/conversations', ['user_id' => 1])->assertForbidden();
    }

    public function test_send_uses_session_identity_and_retries_do_not_duplicate_messages(): void
    {
        $conversation = $this->conversation();
        $payload = ['body' => '  Hello colleague  ', 'client_token' => (string) Str::uuid(), 'sender_id' => 3, 'read_at' => now()];
        $url = '/staff-messaging/conversations/' . $conversation->id . '/messages';
        $id = $this->postJson($url, $payload)->assertCreated()->assertJsonPath('message.sender_id', 1)
            ->assertJsonPath('message.body', 'Hello colleague')->assertJsonPath('message.read_at', null)->json('message.id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('message.id', $id);
        $this->assertSame(1, StaffMessage::count());
        $this->postJson($url, array_replace($payload, ['body' => 'Different message']))->assertConflict();
        Event::assertDispatchedTimes(StaffMessagingUpdated::class, 1);
        Event::assertDispatched(StaffMessagingUpdated::class, function ($event) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
            $this->assertSame(['private-staff-messaging.user.1', 'private-staff-messaging.user.2'], $channels);
            $this->assertArrayNotHasKey('body', get_object_vars($event));
            return $event->kind === 'message_created';
        });
    }

    public function test_nonparticipant_director_cannot_read_send_or_mark_messages_read(): void
    {
        $conversation = $this->conversation();
        $message = $this->message($conversation, 1, 'Private content');
        $this->actingAs(User::findOrFail(3));
        Gate::before(fn () => true);
        $base = '/staff-messaging/conversations/' . $conversation->id;
        $this->getJson($base . '/messages')->assertNotFound();
        $this->postJson($base . '/messages', ['body' => 'Intrusion', 'client_token' => (string) Str::uuid()])->assertNotFound();
        $this->postJson($base . '/read', ['through_id' => $message->id])->assertNotFound();
        $this->getJson('/staff-messaging/conversations')->assertJsonPath('conversations', []);
        $this->getJson('/staff-messaging/unread-count')->assertJsonPath('unread_count', 0);
        $this->assertNull($message->fresh()->read_at);
    }

    public function test_live_delivery_failure_preserves_the_saved_message_and_safe_retry(): void
    {
        $dispatcher = \Mockery::mock(Event::getFacadeRoot())->makePartial();
        $dispatcher->shouldReceive('dispatch')
            ->with(\Mockery::type(StaffMessagingUpdated::class))
            ->andThrow(new \RuntimeException('Simulated live delivery outage'));
        Event::swap($dispatcher);
        $conversation = $this->conversation();
        $payload = ['body' => 'Saved during an outage', 'client_token' => (string) Str::uuid()];
        $url = '/staff-messaging/conversations/' . $conversation->id . '/messages';
        $id = $this->postJson($url, $payload)->assertCreated()->json('message.id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('message.id', $id);
        $this->assertSame(1, StaffMessage::count());
    }

    public function test_read_markers_only_change_received_messages_through_the_displayed_boundary(): void
    {
        $conversation = $this->conversation();
        $first = $this->message($conversation, 1, 'First');
        $own = $this->message($conversation, 2, 'Reply');
        $later = $this->message($conversation, 1, 'Later');
        $this->actingAs(User::findOrFail(2));
        $this->getJson('/staff-messaging/unread-count')->assertJsonPath('unread_count', 2);
        $this->postJson('/staff-messaging/conversations/' . $conversation->id . '/read', ['through_id' => $own->id])
            ->assertOk()->assertJsonPath('updated', 1)->assertJsonPath('unread_count', 1);
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($own->fresh()->read_at);
        $this->assertNull($later->fresh()->read_at);
        $this->getJson('/staff-messaging/unread-count')->assertJsonPath('unread_count', 1);
    }

    public function test_history_and_incremental_updates_are_ordered_and_paginated(): void
    {
        $conversation = $this->conversation();
        foreach (range(1, 55) as $number) $this->message($conversation, 1, 'Message ' . $number);
        $url = '/staff-messaging/conversations/' . $conversation->id . '/messages';
        $recent = $this->getJson($url)->assertOk()->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true);
        $this->assertSame('Message 6', $recent->json('messages.0.body'));
        $this->getJson($url . '?before_id=6')->assertOk()->assertJsonCount(5, 'messages')->assertJsonPath('has_more', false);
        $this->getJson($url . '?after_id=50')->assertOk()->assertJsonCount(5, 'messages')->assertJsonPath('messages.0.body', 'Message 51');
        $this->getJson($url . '?before_id=6&after_id=50')->assertUnprocessable();
        $this->getJson('/staff-messaging/conversations')->assertJsonPath('conversations.0.latest_message.body', 'Message 55');
        $this->assertSame(55, StaffMessage::whereNull('read_at')->count());
    }

    public function test_blank_messages_and_foreign_read_markers_are_rejected(): void
    {
        $conversation = $this->conversation();
        $other = $this->conversation(2, 3);
        $foreign = $this->message($other, 2);
        $base = '/staff-messaging/conversations/' . $conversation->id;
        $this->postJson($base . '/messages', ['body' => " \n ", 'client_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson($base . '/messages', ['body' => str_repeat('x', 5001), 'client_token' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson($base . '/read', ['through_id' => $foreign->id])->assertUnprocessable();
        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_guest_and_other_users_cannot_authorize_the_private_channel(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test-app',
            'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-staff-messaging.user.1'])
            ->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-staff-messaging.user.2'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/staff-messaging/conversations')->assertUnauthorized();
    }
}

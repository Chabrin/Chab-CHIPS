<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'notification_feed_test']);
        config(['database.connections.notification_feed_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('notifiable_type');
            $table->integer('notifiable_id');
            $table->text('data');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('action_url')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }

    private function notification(int $userId, bool $read = false): Notification
    {
        return Notification::create([
            'type' => 'LeaveApplicationSubmitted', 'notifiable_type' => User::class,
            'notifiable_id' => $userId, 'data' => ['private' => 'not part of the feed'],
            'title' => 'Leave application', 'message' => 'Please review.',
            'read_at' => $read ? now() : null,
        ]);
    }

    private function feed(int $userId, ?int $after = null): array
    {
        $request = Request::create('/notifications/feed', 'GET', $after === null ? [] : ['after_id' => $after]);
        $request->setUserResolver(fn () => (new User())->forceFill(['id' => $userId]));
        $response = app(NotificationController::class)->feed($request);
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        return $response->getData(true);
    }

    public function test_initial_feed_counts_only_the_users_unread_notifications_without_replaying_old_toasts(): void
    {
        $own = $this->notification(1);
        $latest = $this->notification(1, true);
        $this->notification(2);
        $feed = $this->feed(1);
        $this->assertSame(1, $feed['unread_count']);
        $this->assertSame([], $feed['notifications']);
        $this->assertSame($latest->id, $feed['cursor']);

        $fresh = $this->notification(1);
        $this->notification(2);
        $feed = $this->feed(1, $latest->id);
        $this->assertSame([$fresh->id], array_column($feed['notifications'], 'id'));
        $this->assertArrayNotHasKey('data', $feed['notifications'][0]);
        $this->assertSame([], $this->feed(1, $feed['cursor'])['notifications']);
    }

    public function test_burst_arrivals_are_paged_without_losing_notifications(): void
    {
        $expected = [];
        for ($i = 0; $i < 55; $i++) {
            $expected[] = $this->notification(1)->id;
        }
        $first = $this->feed(1, 0);
        $this->assertCount(50, $first['notifications']);
        $this->assertTrue($first['has_more']);
        $second = $this->feed(1, $first['cursor']);
        $this->assertCount(5, $second['notifications']);
        $this->assertFalse($second['has_more']);
        $actual = array_merge(array_column($first['notifications'], 'id'), array_column($second['notifications'], 'id'));
        $this->assertSame($expected, $actual);
    }

    public function test_another_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        $notification = $this->notification(1);
        $this->actingAs((new User())->forceFill(['id' => 2]));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(NotificationController::class)->markAsRead($notification->id);
    }
}

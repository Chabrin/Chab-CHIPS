<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class StaffMessagingUpdated implements ShouldBroadcastNow
{
    public function __construct(
        private array $participantIds,
        public string $kind,
        public int $conversation_id,
        public int $actor_id,
        public ?int $message_id = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return array_map(
            fn ($id) => new PrivateChannel('staff-messaging.user.' . $id),
            $this->participantIds
        );
    }

    public function broadcastAs(): string
    {
        return 'staff.messaging.updated';
    }
}

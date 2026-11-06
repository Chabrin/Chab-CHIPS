<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessagingConnectionTest implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        private int $recipientId,
        public string $message = 'Your private messaging connection works.'
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->recipientId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messaging.test';
    }
}
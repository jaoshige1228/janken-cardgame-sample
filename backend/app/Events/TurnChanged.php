<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TurnChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $roomId,
        public string $phase,
        public ?int $currentTurnSlot,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'TurnChanged';
    }
}

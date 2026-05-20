<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BattleResult implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $roomId,
        public string $card1,
        public string $card2,
        public string $outcome,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'BattleResult';
    }
}

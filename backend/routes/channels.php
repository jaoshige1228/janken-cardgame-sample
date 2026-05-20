<?php

use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('room.{roomId}', function ($user, string $roomId) {
    $token = request()->bearerToken();
    if (! $token) {
        return false;
    }
    $player = Player::where('token', $token)->first();

    return $player && (int) $player->room_id === (int) $roomId;
});

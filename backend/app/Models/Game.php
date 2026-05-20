<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    protected $fillable = [
        'room_id', 'phase', 'current_turn_slot',
        'hand1', 'hand2', 'card1', 'card2', 'winner',
    ];

    protected function casts(): array
    {
        return [
            'hand1' => 'array',
            'hand2' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    protected $fillable = ['code', 'status'];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function game(): HasOne
    {
        return $this->hasOne(Game::class);
    }
}

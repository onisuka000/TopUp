<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'game_id',
        'name',
        'provider_code',
        'cost_price',
        'selling_price',
        'is_active',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}

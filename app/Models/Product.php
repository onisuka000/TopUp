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
        'tokovoucher_price',
        'cost_price',
        'selling_price',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'tokovoucher_price' => 'float',
        'cost_price'        => 'float',
        'selling_price'     => 'float',
        'is_active'         => 'boolean',
        'last_synced_at'    => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'product_id',
        'game_user_id',
        'zone_id',
        'amount',
        'payment_method',
        'status',
        'provider_ref_id',
        'error_message',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = ['name', 'slug', 'image', 'has_zone_id', 'is_active'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * Retrieve a setting value by key with optional fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $record = static::where('key', $key)->first();
            return $record ? $record->value : $default;
        });
    }

    /**
     * Store or update a setting value by key.
     */
    public static function set(string $key, mixed $value, ?string $description = null): static
    {
        Cache::forget("setting.{$key}");

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value'       => is_array($value) ? json_encode($value) : (string) $value,
                'description' => $description,
            ]
        );
    }
}

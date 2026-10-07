<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $fillable = [
        'currency',
        'currency_code',
        'symbol',
        'exchange_rate',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'is_default'    => 'boolean',
        'is_active'     => 'boolean',
    ];

    /**
     * Clear cached currencies on save/delete.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('app.active_currencies');
            Cache::forget('app.currency_rates');
        });
        static::deleted(function () {
            Cache::forget('app.active_currencies');
            Cache::forget('app.currency_rates');
        });
    }

    /**
     * Get active exchange rate for a currency code (e.g. KHR -> 4000, IDR -> 16000).
     */
    public static function getRate(string $code, float $fallback = 1.0): float
    {
        $code = strtoupper(trim($code));

        $rates = Cache::remember('app.currency_rates', 3600, function () {
            return static::where('is_active', true)
                ->pluck('exchange_rate', 'currency_code')
                ->mapWithKeys(fn ($rate, $c) => [strtoupper($c) => (float) $rate])
                ->all();
        });

        return isset($rates[$code]) ? (float) $rates[$code] : $fallback;
    }

    /**
     * Convert an amount from one currency to another using exchange rates relative to base USD.
     */
    public static function convert(float $amount, string $from = 'USD', string $to = 'KHR'): float
    {
        $fromRate = static::getRate($from, 1.0);
        $toRate   = static::getRate($to, 1.0);

        if ($fromRate <= 0) {
            $fromRate = 1.0;
        }

        // 1. Convert to base USD
        $usdAmount = $amount / $fromRate;

        // 2. Convert to target currency
        return round($usdAmount * $toRate, 2);
    }

    /**
     * Get the default/base currency (usually USD).
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first()
            ?? static::where('currency_code', 'USD')->first()
            ?? static::first();
    }
}

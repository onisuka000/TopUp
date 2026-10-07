<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MobileLegendsSeeder extends Seeder
{
    /**
     * Run the database seeds for Mobile Legends: Bang Bang.
     */
    public function run(): void
    {
        $game = Game::updateOrCreate(
            ['slug' => 'mobile-legends'],
            [
                'name'        => 'Mobile Legends: Bang Bang',
                'image'       => '/images/games/mlbb.png',
                'has_zone_id' => true,
                'is_active'   => true,
            ]
        );

        $exchangeRate = 16000.0; // 1 USD = 16,000 IDR
        $marginMultiplier = 1.15; // 15% markup for selling price

        $packages = [
            ['name' => '86 Diamonds',              'code' => 'MLA86',      'idr' => 25393],
            ['name' => '172 Diamonds',             'code' => 'MLA172',     'idr' => 49773],
            ['name' => '257 Diamonds',             'code' => 'MLA257',     'idr' => 74107],
            ['name' => '343 Diamonds',             'code' => 'MLA343',     'idr' => 98972],
            ['name' => '429 Diamonds',             'code' => 'MLA429',     'idr' => 123315],
            ['name' => '514 Diamonds',             'code' => 'MLA514',     'idr' => 147670],
            ['name' => '706 Diamonds',             'code' => 'MLA706',     'idr' => 199135],
            ['name' => '2195 Diamonds',            'code' => 'MLA2195',    'idr' => 582940],
            ['name' => 'Weekly Diamond Pass (WDP)', 'code' => 'MLBBGLOWDP', 'idr' => 26958],
        ];

        foreach ($packages as $pkg) {
            $costUsd = round($pkg['idr'] / $exchangeRate, 2);
            $sellUsd = round($costUsd * $marginMultiplier, 2);

            Product::updateOrCreate(
                [
                    'game_id'       => $game->id,
                    'provider_code' => $pkg['code'],
                ],
                [
                    'name'              => $pkg['name'],
                    'tokovoucher_price' => $pkg['idr'],
                    'cost_price'        => $costUsd,
                    'selling_price'     => $sellUsd,
                    'is_active'         => true,
                    'last_synced_at'    => now(),
                ]
            );
        }
    }
}

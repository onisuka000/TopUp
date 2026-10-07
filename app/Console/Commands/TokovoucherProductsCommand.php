<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Services\TokovoucherService;
use Illuminate\Console\Command;

class TokovoucherProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokovoucher:products 
                            {code=ML : Product code prefix (e.g. ML for Mobile Legends)} 
                            {--sync : Automatically sync fetched products into local database}
                            {--rate=16000 : Approximate exchange rate IDR per 1 USD for cost_price}
                            {--margin=1.15 : Markup multiplier for selling price (e.g. 1.15 for 15% markup)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and display/sync Tokovoucher products for Mobile Legends (or given code)';

    /**
     * Execute the console command.
     */
    public function handle(TokovoucherService $tokovoucherService): int
    {
        $code = (string) $this->argument('code');
        $this->info("Fetching Tokovoucher products for prefix: '{$code}'...");

        $response = $tokovoucherService->getProducts($code);

        if (($response['status'] ?? 0) != 1) {
            $this->error('Failed to fetch products: ' . ($response['error_msg'] ?? $response['message'] ?? 'Unknown error'));
            if (isset($response['raw'])) {
                $this->line($response['raw']);
            }
            return Command::FAILURE;
        }

        $items = $response['data'] ?? [];

        if (empty($items)) {
            $this->warn("No products found for code prefix '{$code}'.");
            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($items as $item) {
            $tableRows[] = [
                $item['code'] ?? '-',
                $item['nama_produk'] ?? '-',
                number_format($item['price'] ?? 0) . ' IDR',
                ($item['status'] ?? 0) == 1 ? 'Aktif' : 'Non-aktif',
            ];
        }

        $this->table(['Code (SKU)', 'Product Name', 'Price', 'Status'], $tableRows);
        $this->info('Total products: ' . count($items));

        if ($this->option('sync')) {
            $this->info('Syncing products into database...');

            $game = Game::where('slug', 'mobile-legends')
                ->orWhere('name', 'like', '%Mobile Legends%')
                ->first();

            if (!$game) {
                $game = Game::create([
                    'name'        => 'Mobile Legends: Bang Bang',
                    'slug'        => 'mobile-legends',
                    'has_zone_id' => true,
                    'is_active'   => true,
                ]);
                $this->info("Created new game record: {$game->name} (ID: {$game->id})");
            }

            $rate   = (float) $this->option('rate') ?: 16000;
            $margin = (float) $this->option('margin') ?: 1.15;
            $syncedCount = 0;

            foreach ($items as $item) {
                $sku = $item['code'] ?? null;
                if (!$sku) {
                    continue;
                }

                $priceIdr  = (float) ($item['price'] ?? 0);
                $costUsd   = round($priceIdr / $rate, 2);
                $sellUsd   = round($costUsd * $margin, 2);

                Product::updateOrCreate(
                    [
                        'game_id'       => $game->id,
                        'provider_code' => $sku,
                    ],
                    [
                        'name'          => $item['nama_produk'] ?? $sku,
                        'cost_price'    => $costUsd,
                        'selling_price' => $sellUsd,
                        'is_active'     => ($item['status'] ?? 0) == 1,
                    ]
                );

                $syncedCount++;
            }

            $this->info("Successfully synced {$syncedCount} products to game: {$game->name}");
        }

        return Command::SUCCESS;
    }
}

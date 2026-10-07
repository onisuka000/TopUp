<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('currency'); // e.g. US Dollar, Khmer Riel
                $table->string('currency_code', 10)->unique(); // e.g. USD, KHR, IDR
                $table->string('symbol', 10); // e.g. $, ៛, Rp
                $table->decimal('exchange_rate', 16, 4)->default(1.0000); // Relative to USD
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed default currencies: USD (Base), KHR (4000), IDR (16000 for Tokovoucher), THB (36)
            DB::table('currencies')->insert([
                [
                    'currency'      => 'US Dollar',
                    'currency_code' => 'USD',
                    'symbol'        => '$',
                    'exchange_rate' => 1.0000,
                    'is_default'    => true,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'currency'      => 'Khmer Riel',
                    'currency_code' => 'KHR',
                    'symbol'        => '៛',
                    'exchange_rate' => 4000.0000,
                    'is_default'    => false,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'currency'      => 'Indonesian Rupiah',
                    'currency_code' => 'IDR',
                    'symbol'        => 'Rp',
                    'exchange_rate' => 16000.0000,
                    'is_default'    => false,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'currency'      => 'Thai Baht',
                    'currency_code' => 'THB',
                    'symbol'        => '฿',
                    'exchange_rate' => 36.0000,
                    'is_default'    => false,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};

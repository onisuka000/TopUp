<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'tokovoucher_price')) {
                $table->decimal('tokovoucher_price', 14, 2)->nullable()->after('provider_code')
                    ->comment('Original supplier price in IDR (Indonesian Rupiah) from Tokovoucher');
            }
            if (!Schema::hasColumn('products', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('is_active')
                    ->comment('Timestamp when price was last synced from Tokovoucher');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'tokovoucher_price')) {
                $table->dropColumn('tokovoucher_price');
            }
            if (Schema::hasColumn('products', 'last_synced_at')) {
                $table->dropColumn('last_synced_at');
            }
        });

        Schema::dropIfExists('settings');
    }
};

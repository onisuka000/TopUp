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
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('game_id')->constrained()->cascadeOnDelete();
        $table->string('name'); // ឧ. 86 Diamonds
        $table->string('provider_code'); // SKU កូដកញ្ចប់របស់ Provider (ឧ. mlbb_86)
        $table->decimal('cost_price', 8, 2); // ថ្លៃដើមទិញពីគេ (USD)
        $table->decimal('selling_price', 8, 2); // តម្លៃលក់ឱ្យអតិថិជន (USD)
        $table->boolean('is_active')->default(true); // បិទ/បើកស្តុក
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

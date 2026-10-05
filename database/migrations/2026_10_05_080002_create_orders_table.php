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
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_number')->unique(); // ឧ. ORD-20261005-XXXX
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();
        $table->string('game_user_id'); // ID គណនីអ្នកលេង
        $table->string('zone_id')->nullable(); // Server / Zone ID
        $table->decimal('amount', 8, 2); // ចំនួនទឹកប្រាក់ ($)
        $table->string('payment_method')->default('KHQR');
        $table->string('status')->default('PENDING'); // PENDING, PAID, PROCESSING, COMPLETED, FAILED
        $table->string('provider_ref_id')->nullable(); // លេខ Order ID របស់ Provider (ពេលជោគជ័យ)
        $table->text('error_message')->nullable(); // កត់ត្រា Error បើសិនជា Provider បដិសេធ
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

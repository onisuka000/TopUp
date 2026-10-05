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
    Schema::create('games', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // ឧ. Mobile Legends
        $table->string('slug')->unique(); // ឧ. mlbb
        $table->string('image')->nullable(); // រូបភាព Logo ហ្គេម
        $table->boolean('has_zone_id')->default(true); // ហ្គេមខ្លះទាមទារ Zone ID ខ្លះអត់
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

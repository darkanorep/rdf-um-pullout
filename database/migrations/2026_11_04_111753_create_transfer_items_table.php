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
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->restrictOnDelete();
            $table->foreignId('bonded_item_id')->unique()->constrained('bonded_items')->restrictOnDelete();
            $table->foreignId('origin_inventory_id')->constrained('inventories')->restrictOnDelete();
            $table->foreignId('received_inventory_id')->constrained('inventories')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
    }
};

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
        Schema::create('move_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('move_order_id')->constrained('move_orders');
            $table->foreignId('inventory_id')->constrained('inventories');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('move_inventories');
    }
};

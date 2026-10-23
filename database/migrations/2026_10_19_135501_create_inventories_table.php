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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('uom_id')->constrained('unit_of_measurements');
            $table->float('actual_received', 10,)->nullable();
            $table->foreignId('account_title_id')->constrained('account_titles');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};

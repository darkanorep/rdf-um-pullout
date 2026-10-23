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
        Schema::create('miscellaneous_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('misc_receipt_id')->nullable()->constrained('miscellaneous_receipts');
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->foreignId('uom_id')->nullable()->constrained('unit_of_measurements');
            $table->foreignId('account_title_id')->nullable()->constrained('account_titles');
            $table->float('quantity', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('miscellaneous_inventories');
    }
};

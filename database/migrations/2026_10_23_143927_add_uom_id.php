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
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->constrained('unit_of_measurements');
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['uom_id']);
            $table->dropColumn('uom_id');

            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');

            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};

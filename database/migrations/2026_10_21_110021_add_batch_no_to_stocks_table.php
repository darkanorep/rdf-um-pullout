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
        Schema::table('stocks', function (Blueprint $table): void {
            $table->string('batch_no')->nullable()->unique()->after('id');
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->float('quantity_received')->nullable()->after('uom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table): void {
            $table->dropUnique(['batch_no']);
            $table->dropColumn('batch_no');
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropColumn('quantity_received');
        });
    }
};

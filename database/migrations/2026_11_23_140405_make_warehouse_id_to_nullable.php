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
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->change();
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('warehouse_id')->nullable()->change();
        });
    }
};

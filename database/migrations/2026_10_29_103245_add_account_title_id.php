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
        Schema::table('move_inventories', function (Blueprint $table) {
            $table->foreignId('account_title_id')->nullable()->constrained('account_titles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('move_inventories', function (Blueprint $table) {
            $table->dropForeign(['account_title_id']);
            $table->dropColumn('account_title_id');
        });
    }
};

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
        Schema::table('account_titles', function (Blueprint $table) {
            $table->unique(['code', 'sync_id'], 'account_titles_code_sync_id_unique');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->unique(['code', 'sync_id'], 'charges_code_sync_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_titles', function (Blueprint $table) {
            $table->dropUnique('account_titles_code_sync_id_unique');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->dropUnique('charges_code_sync_id_unique');
        });
    }
};

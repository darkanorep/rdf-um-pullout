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
        Schema::create('account_titles', function (Blueprint $table) {
            $table->id();
            $table->integer('sync_id')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('account_type_name')->nullable();
            $table->string('account_group_name')->nullable();
            $table->string('account_sub_group_name')->nullable();
            $table->string('financial_statement_name')->nullable();
            $table->string('normal_balance_name')->nullable();
            $table->string('allocation_name')->nullable();
            $table->string('account_unit_name')->nullable();
            $table->string('charge')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_titles');
    }
};

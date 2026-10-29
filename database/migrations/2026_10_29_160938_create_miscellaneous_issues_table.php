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
        Schema::create('miscellaneous_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miscellaneous_id')->nullable()->constrained('miscellaneous');
            $table->foreignId('inventory_id')->nullable()->constrained('inventories');
            $table->decimal('quantity', 14, 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('miscellaneous_issues');
    }
};

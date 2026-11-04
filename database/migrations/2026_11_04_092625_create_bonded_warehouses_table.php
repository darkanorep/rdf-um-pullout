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
        Schema::create('bonded_warehouses', function (Blueprint $table) {
            $table->id();
            $table->timestamp('transfer_date')->nullable();
            $table->foreignId('transferred_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('transferred_to')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('release_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonded_warehouses');
    }
};

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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->integer('sync_id')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('company_code')->nullable();
            $table->string('company_name')->nullable();
            $table->string('business_unit_code')->nullable();
            $table->string('business_unit_name')->nullable();
            $table->string('department_code')->nullable();
            $table->string('department_name')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('unit_name')->nullable();
            $table->string('sub_unit_code')->nullable();
            $table->string('sub_unit_name')->nullable();
            $table->string('location_code')->nullable();
            $table->string('location_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};

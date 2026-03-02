<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('ehealth_id')->nullable();
            $table->uuid('patient_id')->nullable();
            
            // Core attributes
            $table->string('category');
            $table->string('intention');
            $table->string('terms_service');
            $table->string('name_treatment_plan');
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->string('status')->default('NEW');
            
            // Specification attributes
            $table->json('addresses')->nullable();
            $table->uuid('encounter')->nullable();
            $table->uuid('author')->nullable();
            $table->text('description')->nullable();
            $table->json('supporting_info')->nullable();
            $table->text('note')->nullable();
            $table->string('inform_with')->nullable();
            
            // Async sync details
            $table->uuid('job_id')->nullable();
            $table->json('validation_details')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};

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
        Schema::create('treatment_plan_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained('treatment_plans')->onDelete('cascade');
            $table->uuid('ehealth_id')->nullable()->unique();
            
            // Core attributes
            $table->string('status')->default('NEW'); // IN_PROGRESS, SCHEDULED, CANCELLED, COMPLETED
            $table->string('detail_kind'); // medication_request, service_request, device_request
            $table->uuid('medical_program_id')->nullable(); // UUID of medical program
            $table->string('code')->nullable(); // Code from dictionary based on detail_kind
            $table->json('code_detail')->nullable(); // Original JSON coding block
            
            $table->string('funding_source')->nullable();
            $table->string('category')->nullable();
            $table->string('reason_condition_id')->nullable();
            
            $table->integer('quantity_value')->nullable();
            $table->string('quantity_system')->nullable();
            $table->string('quantity_code')->nullable();
            
            $table->text('instruction')->nullable();
            $table->json('timing')->nullable(); // For dosage/service schedules
            
            // Optional sync specifics
            $table->uuid('job_id')->nullable();
            $table->json('validation_details')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_activities');
    }
};

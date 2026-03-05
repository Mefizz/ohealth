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
        Schema::create('treatment_plan_activities', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->nullable()->unique(); // eHealth UUID
            $table->foreignId('treatment_plan_id')->constrained('treatment_plans')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('employees');
            
            $table->string('status')->default('scheduled');
            $table->boolean('do_not_perform')->default(false);
            
            $table->string('kind'); // medication_request, device_request, service_request
            $table->string('product_reference')->nullable(); 
            $table->string('product_codeable_concept')->nullable();
            
            $table->integer('quantity')->nullable();
            $table->string('quantity_system')->nullable();
            $table->string('quantity_code')->nullable();
            
            $table->decimal('daily_amount', 10, 4)->nullable();
            $table->string('daily_amount_system')->nullable();
            $table->string('daily_amount_code')->nullable();
            
            $table->string('reason_code')->nullable();
            $table->json('reason_reference')->nullable();
            $table->text('goal')->nullable();
            $table->text('description')->nullable();
            $table->uuid('program')->nullable(); // string/uuid of the medical program
            
            $table->date('scheduled_period_start')->nullable();
            $table->date('scheduled_period_end')->nullable();
            
            $table->string('status_reason')->nullable();
            $table->string('outcome_reference')->nullable();
            $table->string('outcome_codeable_concept')->nullable();
            
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

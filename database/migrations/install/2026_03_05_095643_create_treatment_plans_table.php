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
        Schema::create('treatment_plans', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->nullable()->unique(); // eHealth UUID
            $table->foreignId('person_id')->constrained('persons');
            $table->foreignId('author_id')->constrained('employees');
            $table->foreignId('legal_entity_id')->constrained('legal_entities');
            
            $table->string('status')->default('NEW');
            $table->string('category');
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end')->nullable();
            
            $table->string('terms_of_service')->nullable();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters');
            $table->json('addresses')->nullable(); // Medical condition/diagnosis
            
            $table->text('description')->nullable();
            $table->json('supporting_info')->nullable();
            $table->text('note')->nullable();
            $table->string('inform_with')->nullable(); // Authentication method
            
            $table->string('requisition')->nullable(); // Public identifier
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};

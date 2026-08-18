<?php

declare(strict_types=1);

use App\Enums\EmployeeRole\Status;
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
        Schema::create('employee_roles', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('healthcare_service_id')->constrained();
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->enum('status', Status::values());
            $table->boolean('is_active');
            $table->timestamp('ehealth_inserted_at');
            $table->string('ehealth_inserted_by');
            $table->timestamp('ehealth_updated_at');
            $table->string('ehealth_updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_roles');
    }
};

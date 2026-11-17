<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_request_requests', static function (Blueprint $table): void {
            $table->jsonb('resource_metadata')->nullable();
            foreach (['used_by_employee_id', 'used_by_legal_entity_id', 'used_by_division_id', 'completed_with_id', 'program_service_id'] as $column) {
                $table->foreignId($column)->nullable()->constrained('identifiers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_request_requests', static function (Blueprint $table): void {
            foreach (['used_by_employee_id', 'used_by_legal_entity_id', 'used_by_division_id', 'completed_with_id', 'program_service_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn('resource_metadata');
        });
    }
};

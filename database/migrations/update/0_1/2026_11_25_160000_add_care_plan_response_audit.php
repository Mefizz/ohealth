<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['status_history', 'ehealth_payload', 'ehealth_inserted_at', 'ehealth_inserted_by', 'ehealth_updated_at', 'ehealth_updated_by'] as $column) {
            if (!Schema::hasColumn('care_plans', $column)) {
                Schema::table('care_plans', function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'status_history', 'ehealth_payload' => $table->json($column)->nullable(),
                        'ehealth_inserted_at', 'ehealth_updated_at' => $table->timestampTz($column)->nullable(),
                        default => $table->uuid($column)->nullable(),
                    };
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['status_history', 'ehealth_payload', 'ehealth_inserted_at', 'ehealth_inserted_by', 'ehealth_updated_at', 'ehealth_updated_by'] as $column) {
            if (Schema::hasColumn('care_plans', $column)) {
                Schema::table('care_plans', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};

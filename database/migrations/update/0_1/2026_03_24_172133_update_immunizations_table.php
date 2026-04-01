<?php

declare(strict_types=1);

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
        Schema::table('immunizations', function (Blueprint $table) {
            if (!Schema::hasColumn('immunizations', 'person_id')) {
                $table->foreignId('person_id')->after('uuid')->constrained('persons');
            }
            if (Schema::hasColumn('immunizations', 'encounter_id')) {
                $table->foreignId('encounter_id')->nullable()->change();
            }
            if (!Schema::hasColumn('immunizations', 'explanatory_letter')) {
                $table->string('explanatory_letter')->after('expiration_date')->nullable();
            }
            if (!Schema::hasColumn('immunizations', 'ehealth_inserted_at')) {
                $table->timestamp('ehealth_inserted_at')->nullable()->after('route_id');
            }
            if (!Schema::hasColumn('immunizations', 'ehealth_updated_at')) {
                $table->timestamp('ehealth_updated_at')->nullable()->after('ehealth_inserted_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('immunizations', function (Blueprint $table) {
            if (Schema::hasColumn('immunizations', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropColumn('person_id');
            }
            if (Schema::hasColumn('immunizations', 'explanatory_letter')) {
                $table->dropColumn('explanatory_letter');
            }
            if (Schema::hasColumn('immunizations', 'ehealth_inserted_at')) {
                $table->dropColumn('ehealth_inserted_at');
            }
            if (Schema::hasColumn('immunizations', 'ehealth_updated_at')) {
                $table->dropColumn('ehealth_updated_at');
            }
            if (Schema::hasColumn('immunizations', 'encounter_id')) {
                $table->foreignId('encounter_id')->nullable(false)->change();
            }
        });
    }
};

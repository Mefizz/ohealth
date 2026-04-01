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
        Schema::table('clinical_impressions', function (Blueprint $table) {
            if (!Schema::hasColumn('clinical_impressions', 'person_id')) {
                $table->foreignId('person_id')->after('uuid')->constrained('persons');
            }
            if (!Schema::hasColumn('clinical_impressions', 'explanatory_letter')) {
                $table->string('explanatory_letter')->after('note')->nullable();
            }
            if (!Schema::hasColumn('clinical_impressions', 'ehealth_inserted_at')) {
                $table->timestamp('ehealth_inserted_at')->nullable()->after('note');
            }
            if (!Schema::hasColumn('clinical_impressions', 'ehealth_updated_at')) {
                $table->timestamp('ehealth_updated_at')->nullable()->after('ehealth_inserted_at');
            }
            if (Schema::hasColumn('clinical_impressions', 'encounter_internal_id')) {
                $table->foreignId('encounter_internal_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_impressions', function (Blueprint $table) {
            if (Schema::hasColumn('clinical_impressions', 'encounter_internal_id')) {
                $table->foreignId('encounter_internal_id')->nullable(false)->change();
            }
            if (Schema::hasColumn('clinical_impressions', 'explanatory_letter')) {
                $table->dropColumn('explanatory_letter');
            }
            if (Schema::hasColumn('clinical_impressions', 'ehealth_updated_at')) {
                $table->dropColumn('ehealth_updated_at');
            }
            if (Schema::hasColumn('clinical_impressions', 'ehealth_inserted_at')) {
                $table->dropColumn('ehealth_inserted_at');
            }
            if (Schema::hasColumn('clinical_impressions', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropColumn('person_id');
            }
        });
    }
};

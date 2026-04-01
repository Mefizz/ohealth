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
        Schema::table('observations', function (Blueprint $table) {
            if (!Schema::hasColumn('observations', 'person_id')) {
                $table->foreignId('person_id')->after('uuid')->constrained('persons');
            }
            if (!Schema::hasColumn('observations', 'specimen_id')) {
                $table->foreignId('specimen_id')->after('context_id')->nullable()->constrained('identifiers');
            }
            if (!Schema::hasColumn('observations', 'device_id')) {
                $table->foreignId('device_id')->after('specimen_id')->nullable()->constrained('identifiers');
            }
            if (!Schema::hasColumn('observations', 'based_on_id')) {
                $table->foreignId('based_on_id')->after('device_id')->nullable()->constrained('identifiers');
            }
            if (Schema::hasColumn('observations', 'encounter_id')) {
                $table->foreignId('encounter_id')->nullable()->change();
            }
            if (!Schema::hasColumn('observations', 'explanatory_letter')) {
                $table->string('explanatory_letter')->after('based_on_id')->nullable();
            }
            if (!Schema::hasColumn('observations', 'ehealth_inserted_at')) {
                $table->timestamp('ehealth_inserted_at')->nullable()->after('route_id');
            }
            if (!Schema::hasColumn('observations', 'ehealth_updated_at')) {
                $table->timestamp('ehealth_updated_at')->nullable()->after('ehealth_inserted_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            if (Schema::hasColumn('observations', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropColumn('person_id');
            }
            if (Schema::hasColumn('observations', 'based_on_id')) {
                $table->dropForeign(['based_on_id']);
                $table->dropColumn('based_on_id');
            }
            if (Schema::hasColumn('observations', 'specimen_id')) {
                $table->dropForeign(['specimen_id']);
                $table->dropColumn('specimen_id');
            }
            if (Schema::hasColumn('observations', 'device_id')) {
                $table->dropForeign(['device_id']);
                $table->dropColumn('device_id');
            }
            if (Schema::hasColumn('observations', 'explanatory_letter')) {
                $table->dropColumn('explanatory_letter');
            }
            if (Schema::hasColumn('observations', 'ehealth_inserted_at')) {
                $table->dropColumn('ehealth_inserted_at');
            }
            if (Schema::hasColumn('observations', 'ehealth_updated_at')) {
                $table->dropColumn('ehealth_updated_at');
            }
            if (Schema::hasColumn('observations', 'encounter_id')) {
                $table->foreignId('encounter_id')->nullable(false)->change();
            }
        });
    }
};

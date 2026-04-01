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
        Schema::table('observation_components', function (Blueprint $table) {
            if (!Schema::hasColumn('observation_components', 'code_id')) {
                $table->foreignId('code_id')->after('observation_id')->constrained('codeable_concepts');
            }
            if (!Schema::hasColumn('observation_components', 'value_codeable_concept_id')) {
                $table->foreignId('value_codeable_concept_id')->after('interpretation_id')->nullable()->constrained('codeable_concepts');
            }
            if (Schema::hasColumn('observation_components', 'codeable_concept_id')) {
                $table->foreignId('codeable_concept_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('observation_components', function (Blueprint $table) {
            if (Schema::hasColumn('observation_components', 'code_id')) {
                $table->dropForeign(['code_id']);
                $table->dropColumn('code_id');
            }
            if (Schema::hasColumn('observation_components', 'value_codeable_concept_id')) {
                $table->dropForeign(['value_codeable_concept_id']);
                $table->dropColumn('value_codeable_concept_id');
            }
            if (Schema::hasColumn('observation_components', 'codeable_concept_id')) {
                $table->foreignId('codeable_concept_id')->nullable(false)->change();
            }
        });
    }
};

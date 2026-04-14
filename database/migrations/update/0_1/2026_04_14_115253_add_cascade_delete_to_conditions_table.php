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
        Schema::table('conditions', static function (Blueprint $table) {
            // Drop existing foreign key constraints
            $table->dropForeign(['asserter_id']);
            $table->dropForeign(['report_origin_id']);
            $table->dropForeign(['context_id']);
            $table->dropForeign(['code_id']);
            $table->dropForeign(['severity_id']);
            $table->dropForeign(['stage_summary_id']);

            // Add new foreign key constraints with cascade delete
            $table->foreign('asserter_id')->references('id')->on('identifiers')->cascadeOnDelete();
            $table->foreign('report_origin_id')->references('id')->on('codeable_concepts')->cascadeOnDelete();
            $table->foreign('context_id')->references('id')->on('identifiers')->cascadeOnDelete();
            $table->foreign('code_id')->references('id')->on('codeable_concepts')->cascadeOnDelete();
            $table->foreign('severity_id')->references('id')->on('codeable_concepts')->cascadeOnDelete();
            $table->foreign('stage_summary_id')->references('id')->on('codeable_concepts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conditions', static function (Blueprint $table) {
            // Drop cascade delete constraints
            $table->dropForeign(['asserter_id']);
            $table->dropForeign(['report_origin_id']);
            $table->dropForeign(['context_id']);
            $table->dropForeign(['code_id']);
            $table->dropForeign(['severity_id']);
            $table->dropForeign(['stage_summary_id']);

            // Restore original constraints without cascade
            $table->foreign('asserter_id')->references('id')->on('identifiers');
            $table->foreign('report_origin_id')->references('id')->on('codeable_concepts');
            $table->foreign('context_id')->references('id')->on('identifiers');
            $table->foreign('code_id')->references('id')->on('codeable_concepts');
            $table->foreign('severity_id')->references('id')->on('codeable_concepts');
            $table->foreign('stage_summary_id')->references('id')->on('codeable_concepts');
        });
    }
};

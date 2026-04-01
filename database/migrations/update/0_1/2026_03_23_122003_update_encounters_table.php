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
        Schema::table('encounters', function (Blueprint $table) {
            if (!Schema::hasColumn('encounters', 'performer_speciality_id')) {
                $table->foreignId('performer_speciality_id')->nullable()->after('performer_id')->constrained('codeable_concepts');
            }
            if (Schema::hasColumn('encounters', 'visit_id')) {
                $table->foreignId('visit_id')->nullable()->change();
            }
            if (Schema::hasColumn('encounters', 'performer_id')) {
                $table->foreignId('performer_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            if (Schema::hasColumn('encounters', 'performer_speciality_id')) {
                $table->dropForeign(['performer_speciality_id']);
                $table->dropColumn('performer_speciality_id');
            }
            if (Schema::hasColumn('encounters', 'visit_id')) {
                $table->foreignId('visit_id')->nullable(false)->change();
            }
            if (Schema::hasColumn('encounters', 'performer_id')) {
                $table->foreignId('performer_id')->nullable(false)->change();
            }
        });
    }
};

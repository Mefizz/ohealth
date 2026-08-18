<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align employee_roles date columns with the eHealth convention (plain timestamp).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('employee_roles', static function (Blueprint $table) {
            if (Schema::hasColumn('employee_roles', 'start_date')) {
                $table->timestamp('start_date')->change();
            }

            if (Schema::hasColumn('employee_roles', 'end_date')) {
                $table->timestamp('end_date')->nullable()->change();
            }

            if (Schema::hasColumn('employee_roles', 'ehealth_inserted_at')) {
                $table->timestamp('ehealth_inserted_at')->change();
            }

            if (Schema::hasColumn('employee_roles', 'ehealth_updated_at')) {
                $table->timestamp('ehealth_updated_at')->change();
            }
        });
    }

    /**
     * Revert employee_roles date columns to timezone-aware timestamps.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('employee_roles', static function (Blueprint $table) {
            if (Schema::hasColumn('employee_roles', 'start_date')) {
                $table->dateTimeTz('start_date')->change();
            }

            if (Schema::hasColumn('employee_roles', 'end_date')) {
                $table->dateTimeTz('end_date')->nullable()->change();
            }

            if (Schema::hasColumn('employee_roles', 'ehealth_inserted_at')) {
                $table->dateTimeTz('ehealth_inserted_at')->change();
            }

            if (Schema::hasColumn('employee_roles', 'ehealth_updated_at')) {
                $table->dateTimeTz('ehealth_updated_at')->change();
            }
        });
    }
};

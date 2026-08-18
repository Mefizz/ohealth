<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('prepersons', static function (Blueprint $table): void {
            $table->string('external_id')
                ->nullable()
                ->comment('Identifier from external system')
                ->change();

            if (!Schema::hasColumn('prepersons', 'reason_context')) {
                $table->jsonb('reason_context')
                    ->nullable()
                    ->comment('Local-only structured reason context for editing drafts');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('prepersons', static function (Blueprint $table): void {
            if (Schema::hasColumn('prepersons', 'reason_context')) {
                $table->dropColumn('reason_context');
            }

            $table->string('external_id')
                ->nullable(false)
                ->comment('Identifier from external system')
                ->change();
        });
    }
};

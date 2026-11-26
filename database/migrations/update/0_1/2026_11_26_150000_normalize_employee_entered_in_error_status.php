<?php

declare(strict_types=1);

use App\Enums\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->where('status', 'ENTERED IN ERROR')
            ->update(['status' => Status::ENTERED_IN_ERROR->value]);
    }
};

<?php

declare(strict_types=1);

namespace Tests\Feature\Composition;

use App\Models\MedicalEvents\Sql\Composition;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\CompositionRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompositionFhirMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public static function migrationEntrypoints(): array
    {
        return [
            'artisan migrate' => ['database/migrations/2026_10_24_160000_preserve_composition_fhir_details.php'],
            'project update' => ['database/migrations/update/0_1/2026_10_24_160000_preserve_composition_fhir_details.php'],
        ];
    }

    #[DataProvider('migrationEntrypoints')]
    public function test_upgrade_preserves_existing_conclusions_and_enables_complete_import(string $path): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Schema::table('compositions', static fn (Blueprint $table) => $table->dropColumn('fhir_metadata'));
        $person = Person::create(['uuid' => (string) Str::uuid(), 'birth_date' => '1990-01-01', 'gender' => 'MALE']);
        $composition = Composition::create([
            'uuid' => (string) Str::uuid(), 'person_id' => $person->id,
            'status' => 'PRELIMINARY', 'title' => 'Existing conclusion',
        ]);

        $migration = require base_path($path);
        $migration->up();
        app(CompositionRepository::class)->assertFhirStorageReady();
        $updated = app(CompositionRepository::class)->store([
            'identifier' => ['value' => $composition->uuid],
            'date' => '2026-10-26T07:32:54.791768Z',
            'extension' => [['valueCode' => 'IS_ACCIDENT', 'valueBoolean' => false]],
            'event' => [],
        ], $person);
        $migration->up();

        $this->assertSame($composition->id, $updated->id);
        $this->assertSame('Existing conclusion', $updated->title);
        $this->assertSame(false, $updated->fresh()->fhirMetadata['extension'][0]['valueBoolean']);
        $this->assertSame('2026-10-26T07:32:54.791768Z', $updated->fresh()->toDetail()['date']);
        foreach ([['compositions', 'date'], ['periods', 'start'], ['periods', 'end']] as [$table, $column]) {
            $precision = DB::table('information_schema.columns')->where('table_schema', 'public')
                ->where('table_name', $table)->where('column_name', $column)->value('datetime_precision');
            $this->assertSame(6, (int) $precision);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\CarePlan;

use Tests\TestCase;
use App\Repositories\CarePlanRepository;
use App\Repositories\CarePlanActivityRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CarePlanFhirPersistenceTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as beginIsolatedDatabaseTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        // A private transactional schema keeps the install/update contract independent
        // of the ordinary CI schema, without dropping or modifying existing tables.
        $schemaName = 'care_plan_contract_'.bin2hex(random_bytes(8));
        config(['database.connections.pgsql.search_path' => $schemaName]);
        DB::purge('pgsql');
        $this->beginIsolatedDatabaseTransaction();
        DB::statement('CREATE SCHEMA "'.$schemaName.'"');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->assertFalse(Schema::hasTable('care_plans'), 'The private contract schema must be empty. Existing tables are never dropped.');
        Http::preventStrayRequests();
        foreach (['persons', 'legal_entities', 'employees', 'encounters'] as $name) {
            Schema::create($name, static function (Blueprint $table) use ($name): void {
                $table->id();
                $table->uuid('uuid')->nullable();
                if ($name === 'employees') {
                    $table->unsignedBigInteger('legal_entity_id');
                }
            });
        }
        Schema::create('identifiers', static function (Blueprint $table): void {
            $table->id();
            $table->string('value');
            $table->string('display_value')->nullable();
            $table->timestamps();
        });
        Schema::create('codeable_concepts', static function (Blueprint $table): void {
            $table->id();
            $table->text('text')->nullable();
            $table->nullableMorphs('codeable_conceptable');
            $table->timestamps();
        });
        foreach (['2025_12_10_000052_create_codings_table.php', '2025_12_10_000066_create_periods_table.php', '2025_12_10_000072_create_quantities_table.php', '2026_04_16_000000_create_care_plans_full.php', '2026_10_19_081226_complete_care_plan_fhir_storage.php'] as $file) {
            (require database_path('migrations/install/'.$file))->up();
        }
        DB::table('persons')->insert(['id' => 1]);
        DB::table('legal_entities')->insert(['id' => 1]);
    }

    public function test_full_resources_survive_sql_round_trip_and_repeat_import_without_duplicates(): void
    {
        $planResource = json_decode(file_get_contents(base_path('tests/Fixtures/CarePlan/plan.json')), true, flags: JSON_THROW_ON_ERROR);
        $activityResource = json_decode(file_get_contents(base_path('tests/Fixtures/CarePlan/activity.json')), true, flags: JSON_THROW_ON_ERROR);
        $activityResource['detail']['reasonCode'][0]['coding'][] = ['system' => 'synthetic/second', 'code' => 'SECOND', 'display' => 'Second coding', 'userSelected' => false];
        $plans = app(CarePlanRepository::class);
        $activities = app(CarePlanActivityRepository::class);
        $plan = $plans->storeResource($planResource, 1, 1);
        $activity = $activities->storeResource($activityResource, $plan);
        $counts = $this->primitiveCounts();

        $plans->storeResource($planResource, 1, 1);
        $activities->storeResource($activityResource, $plan);
        $actualPlan = $plans->getResource($plan->id);
        $actualActivity = $activities->getResource($activity->id);

        $this->assertSame($counts, $this->primitiveCounts());
        $this->assertSame($planResource['period'], $actualPlan['period']);
        $this->assertSame($planResource['category'], $actualPlan['category']);
        $this->assertEquals($planResource['supportingInfo'], $actualPlan['supportingInfo']);
        $this->assertEquals($activityResource['author'], $actualActivity['author']);
        $this->assertSame($activityResource['detail']['reasonCode'], $actualActivity['detail']['reasonCode']);
        $this->assertSame($activityResource['detail']['goal'], $actualActivity['detail']['goal']);
        $this->assertSame(1.2345, $actualActivity['detail']['quantity']['value']);
        $this->assertEquals(0, $actualActivity['detail']['remainingQuantity']['value']);
        $this->assertFalse($actualActivity['detail']['doNotPerform']);
        $this->assertFalse($actualActivity['detail']['reasonCode'][0]['coding'][1]['userSelected']);
        $this->assertNull($plan->authorId, 'An unknown remote author is preserved as a reference, not replaced by another employee.');
        $this->assertSame($activityResource['detail']['scheduledPeriod'], $actualActivity['detail']['scheduledPeriod']);
    }

    public function test_clearing_a_collection_removes_its_links(): void
    {
        $resource = json_decode(file_get_contents(base_path('tests/Fixtures/CarePlan/plan.json')), true, flags: JSON_THROW_ON_ERROR);
        $repository = app(CarePlanRepository::class);
        $plan = $repository->storeResource($resource, 1, 1);
        $resource['supportingInfo'] = [];
        $repository->storeResource($resource, 1, 1);

        $this->assertSame([], $repository->getResource($plan->id)['supportingInfo']);
    }

    public function test_over_precision_quantity_is_rejected_without_partial_persistence(): void
    {
        $planResource = json_decode(file_get_contents(base_path('tests/Fixtures/CarePlan/plan.json')), true, flags: JSON_THROW_ON_ERROR);
        $resource = json_decode(file_get_contents(base_path('tests/Fixtures/CarePlan/activity.json')), true, flags: JSON_THROW_ON_ERROR);
        $plan = app(CarePlanRepository::class)->storeResource($planResource, 1, 1);
        $counts = $this->primitiveCounts();
        $resource['detail']['quantity']['value'] = '1.12345678901';
        try {
            app(CarePlanActivityRepository::class)->storeResource($resource, $plan);
            $this->fail('An over-precision quantity must not be rounded by SQL.');
        } catch (InvalidArgumentException) {
            $this->assertSame($counts, $this->primitiveCounts());
            $this->assertSame(0, DB::table('care_plan_activities')->count());
        }
    }

    private function primitiveCounts(): array
    {
        $counts = [];
        foreach (['identifiers', 'codeable_concepts', 'codings', 'periods', 'quantities'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    public function test_preflight_reports_registered_legacy_records_without_changing_them(): void
    {
        DB::table('care_plans')->insert(['id' => 10, 'person_id' => 1, 'legal_entity_id' => 1, 'uuid' => '8d08ce22-82dd-4ac4-bdb9-432fd5aad5dd', 'title' => 'Legacy', 'status' => 'active', 'period_start' => '2026-10-19']);
        $before = DB::table('care_plans')->find(10);

        $report = app(CarePlanRepository::class)->backfillResources(1, true);

        $this->assertSame([['type' => 'carePlan', 'localId' => 10]], $report['needsRemoteSync']);
        $this->assertSame(0, $report['converted']);
        $this->assertEquals($before, DB::table('care_plans')->find(10));
    }

    public function test_draft_preflight_is_read_only_and_backfill_is_repeatable(): void
    {
        DB::table('employees')->insert(['id' => 1, 'uuid' => '47ee824a-eb47-48ef-8d30-1109e3c89f0c', 'legal_entity_id' => 1]);
        DB::table('care_plans')->insert(['id' => 10, 'person_id' => 1, 'legal_entity_id' => 1, 'author_id' => 1, 'title' => 'Draft', 'status' => 'draft', 'period_start' => '2026-10-19', 'category' => 'TEST', 'terms_of_service' => 'OUTPATIENT']);
        $repository = app(CarePlanRepository::class);

        $preview = $repository->backfillResources(1);

        $this->assertSame([], $preview['errors']);
        $this->assertNull(DB::table('care_plans')->where('id', 10)->value('fhir_version'));
        $this->assertNull(DB::table('care_plans')->where('id', 10)->value('uuid'));
        $written = $repository->backfillResources(1, true);
        $resource = $repository->getResource(10);
        $counts = $this->primitiveCounts();
        $again = $repository->backfillResources(1, true);

        $this->assertSame(1, $written['converted']);
        $this->assertSame(0, $again['converted']);
        $this->assertSame($resource, $repository->getResource(10));
        $this->assertSame($counts, $this->primitiveCounts());
    }
}

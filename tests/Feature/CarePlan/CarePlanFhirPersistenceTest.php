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
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->assertFalse(Schema::hasTable('care_plans'), 'Run this contract test in an empty disposable database. Existing tables are never dropped.');
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
        $this->assertSame($planResource['supportingInfo'], $actualPlan['supportingInfo']);
        $this->assertSame($activityResource['author'], $actualActivity['author']);
        $this->assertSame($activityResource['detail']['reasonCode'], $actualActivity['detail']['reasonCode']);
        $this->assertSame($activityResource['detail']['goal'], $actualActivity['detail']['goal']);
        $this->assertSame(1.2345, $actualActivity['detail']['quantity']['value']);
        $this->assertEquals(0, $actualActivity['detail']['remainingQuantity']['value']);
        $this->assertFalse($actualActivity['detail']['doNotPerform']);
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
}

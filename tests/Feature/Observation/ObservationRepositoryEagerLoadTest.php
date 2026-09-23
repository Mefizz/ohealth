<?php

declare(strict_types=1);

namespace Tests\Feature\Observation;

use App\Enums\Person\ObservationStatus;
use App\Models\MedicalEvents\Sql\CodeableConcept;
use App\Models\MedicalEvents\Sql\Identifier;
use App\Models\MedicalEvents\Sql\Observation;
use App\Models\MedicalEvents\Sql\Period;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ObservationRepositoryEagerLoadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_for_encounter_does_not_lazy_load_effective_period(): void
    {
        Model::preventLazyLoading();

        $person = Person::create([
            'uuid' => (string) Str::uuid(),
            'birth_date' => '1990-05-05',
            'gender' => 'MALE',
            'patient_signed' => true,
            'process_disclosure_data_consent' => true,
        ]);

        $encounterUuid = (string) Str::uuid();
        $context = Identifier::create(['value' => $encounterUuid]);
        $code = CodeableConcept::create();

        $observation = Observation::create([
            'uuid' => (string) Str::uuid(),
            'person_id' => $person->id,
            'status' => ObservationStatus::VALID,
            'code_id' => $code->id,
            'context_id' => $context->id,
            'issued' => now(),
            'primary_source' => true,
        ]);

        $period = new Period([
            'start' => now()->subHour(),
            'end' => now(),
        ]);
        $observation->effectivePeriod()->save($period);

        $rows = Repository::observation()->get($encounterUuid);

        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertNotEmpty($rows[0]['effectivePeriodStartDate'] ?? null);
        $this->assertArrayHasKey('effectivePeriod', $rows[0]);
    }

    public function test_get_details_map_by_uuids_does_not_lazy_load_effective_period(): void
    {
        Model::preventLazyLoading();

        $person = Person::create([
            'uuid' => (string) Str::uuid(),
            'birth_date' => '1990-05-05',
            'gender' => 'MALE',
            'patient_signed' => true,
            'process_disclosure_data_consent' => true,
        ]);

        $code = CodeableConcept::create();
        $observation = Observation::create([
            'uuid' => (string) Str::uuid(),
            'person_id' => $person->id,
            'status' => ObservationStatus::VALID,
            'code_id' => $code->id,
            'issued' => now(),
            'primary_source' => true,
        ]);

        $map = Repository::observation()->getDetailsMapByUuids([$observation->uuid]);

        $this->assertArrayHasKey($observation->uuid, $map);
        $this->assertSame('observation', $map[$observation->uuid]['type']);
    }
}

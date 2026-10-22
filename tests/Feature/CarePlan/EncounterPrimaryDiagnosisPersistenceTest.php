<?php

declare(strict_types=1);

namespace Tests\Feature\CarePlan;

use App\Enums\Person\ConditionClinicalStatus;
use App\Enums\Person\ConditionVerificationStatus;
use App\Models\MedicalEvents\Sql\Condition;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\EncounterDiagnose;
use App\Models\MedicalEvents\Sql\Identifier;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\EncounterRepository;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EncounterPrimaryDiagnosisPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    private function encounter(): Encounter
    {
        $person = Person::create([
            'uuid' => (string) Str::uuid(), 'birth_date' => '1990-01-01', 'gender' => 'MALE',
            'patient_signed' => true, 'process_disclosure_data_consent' => true,
        ]);
        $episode = Identifier::create(['value' => (string) Str::uuid()]);
        $class = Repository::coding()->store(['system' => 'eHealth/encounter_classes', 'code' => 'AMB']);
        $type = Repository::codeableConcept()->store(['coding' => [['system' => 'eHealth/encounter_types', 'code' => 'TEST']]]);

        return Encounter::create([
            'uuid' => (string) Str::uuid(), 'person_id' => $person->id, 'status' => 'finished',
            'episode_id' => $episode->id, 'class_id' => $class->id, 'type_id' => $type->id,
            'ehealth_inserted_at' => now(),
        ]);
    }

    private function diagnosis(Encounter $encounter, string $roleCode, string $code, int $rank): Condition
    {
        $identifier = Identifier::create(['value' => (string) Str::uuid()]);
        $concept = Repository::codeableConcept()->store(['coding' => [['system' => 'eHealth/ICD10_AM/condition_codes', 'code' => $code]]]);
        $condition = Condition::create([
            'uuid' => $identifier->value, 'person_id' => $encounter->person_id,
            'primary_source' => true, 'clinical_status' => ConditionClinicalStatus::ACTIVE,
            'verification_status' => ConditionVerificationStatus::CONFIRMED,
            'code_id' => $concept->id, 'context_id' => $encounter->episode_id, 'onset_date' => now(),
        ]);
        $role = Repository::codeableConcept()->store(['coding' => [['system' => 'eHealth/diagnosis_roles', 'code' => $roleCode]]]);
        EncounterDiagnose::create([
            'encounter_id' => $encounter->id, 'condition_id' => $identifier->id,
            'role_id' => $role->id, 'rank' => $rank,
        ]);

        return $condition;
    }

    public function test_varchar_identifier_resolves_primary_uuid_with_codings_under_strict_loading(): void
    {
        $encounter = $this->encounter();
        $this->diagnosis($encounter, 'secondary', 'E10', 1);
        $primary = $this->diagnosis($encounter, 'primary', 'E11', 2);
        $wasPreventing = Model::preventsLazyLoading();
        Model::preventLazyLoading();
        try {
            $conditions = app(EncounterRepository::class)->primaryDiagnosisConditions($encounter->fresh());
            $this->assertSame([$primary->uuid], $conditions->pluck('uuid')->all());
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->assertSame('E11', $conditions->first()->code->coding->first()->code);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            Model::preventLazyLoading($wasPreventing);
        }
    }

    public function test_primary_condition_of_another_patient_is_not_resolved(): void
    {
        $encounter = $this->encounter();
        $otherEncounter = $this->encounter();
        $primary = $this->diagnosis($encounter, 'primary', 'E11', 1);
        $primary->update(['person_id' => $otherEncounter->person_id]);

        $this->assertTrue(app(EncounterRepository::class)->primaryDiagnosisConditions($encounter->fresh())->isEmpty());
    }

    public function test_non_uuid_identifier_does_not_abort_the_postgresql_transaction(): void
    {
        $encounter = $this->encounter();
        $this->diagnosis($encounter, 'primary', 'E11', 1);
        $diagnosis = $encounter->diagnoses()->firstOrFail();
        $diagnosis->condition()->update(['value' => 'not-a-condition-uuid']);

        $this->assertTrue(app(EncounterRepository::class)->primaryDiagnosisConditions($encounter->fresh())->isEmpty());
        $this->assertTrue(Encounter::whereUuid($encounter->uuid)->exists());
    }
}

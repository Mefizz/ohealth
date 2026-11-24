<?php

declare(strict_types=1);

namespace Tests\Feature\CarePlan;

use App\Enums\Status;
use App\Enums\User\Role;
use App\Livewire\CarePlan\CarePlanShow;
use App\Livewire\CarePlan\CarePlanUpdate;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\CodeableConcept;
use App\Models\MedicalEvents\Sql\Coding;
use App\Models\MedicalEvents\Sql\Identifier;
use App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Models\Relations\Party;
use App\Models\User;
use App\Services\Dictionary\Collections\BasicDictionaryCollection;
use App\Services\Dictionary\DictionaryManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\CreatesFhirCarePlans;
use Tests\TestCase;

class CarePlanLifecycleGatesTest extends TestCase
{
    use CreatesFhirCarePlans;

    use DatabaseTransactions;

    protected Person $person;

    protected User $user;

    protected Employee $employee;

    protected LegalEntity $legalEntity;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cipher.api.domain' => 'https://cipher.invalid']);
        Cache::put('knedp_certificate_authority', [], 60);

        $this->person = Person::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Gate',
            'last_name' => 'Patient',
            'birth_date' => '1991-02-02',
            'gender' => 'MALE',
            'patient_signed' => true,
            'process_disclosure_data_consent' => true,
        ]);

        $typeId = DB::table('legal_entity_types')->where('name', 'PRIMARY_CARE')->value('id')
            ?? DB::table('legal_entity_types')->insertGetId(['name' => 'PRIMARY_CARE']);

        $this->legalEntity = LegalEntity::create([
            'uuid' => (string) Str::uuid(),
            'status' => 'ACTIVE',
            'sync_status' => 'COMPLETED',
            'legal_entity_type_id' => $typeId,
            'is_active' => true,
        ]);
        $this->instance('legalEntity', $this->legalEntity);

        $party = Party::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Gate',
            'last_name' => 'Doctor',
            'tax_id' => '1122334455',
            'birth_date' => '1975-01-01',
            'gender' => 'MALE',
        ]);

        $this->user = User::create([
            'uuid' => (string) Str::uuid(),
            'email' => 'gate_' . Str::random(6) . '@example.com',
            'password' => Hash::make('password'),
            'party_id' => $party->id,
        ]);

        $this->employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Dr Gate',
            'employee_type' => Role::DOCTOR->value,
            'status' => Status::APPROVED->value,
            'legal_entity_id' => $this->legalEntity->id,
            'is_active' => true,
            'position' => 'Doctor',
            'start_date' => now()->format('Y-m-d'),
            'user_id' => $this->user->id,
            'party_id' => $party->id,
        ]);

        $this->user->employees()->attach($this->employee->id);

        if (config('permission.teams')) {
            setPermissionsTeamId($this->legalEntity->id);
        }

        $this->grantMedicalEventAbilities($this->user);
    }

    public function test_reopening_a_draft_restores_its_contributors_and_clinical_references(): void
    {
        Http::preventStrayRequests();
        $this->actingAs($this->user);
        $this->mock(DictionaryManager::class)->shouldReceive('basics')->andReturn(new BasicDictionaryCollection([
            ['name' => 'eHealth/care_plan_categories', 'values' => []],
            ['name' => 'eHealth/encounter_classes', 'values' => []],
            ['name' => 'PROVIDING_CONDITION', 'values' => []],
            ['name' => 'AUTHENTICATION_METHOD', 'values' => [
                ['code' => 'OTP', 'description' => 'Одноразовий пароль'],
                ['code' => 'OFFLINE', 'description' => 'Без SMS'],
                ['code' => 'THIRD_PERSON', 'description' => 'Через іншу особу'],
            ]],
        ]));

        $contributors = [(string) Str::uuid(), (string) Str::uuid()];
        $episodeUuid = (string) Str::uuid();
        $authMethod = (string) Str::uuid();
        $record = [
            'identifier' => [
                'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => 'diagnostic_report']]],
                'value' => (string) Str::uuid(),
            ],
            'displayValue' => 'Діагностичний звіт',
        ];
        $draft = $this->createFhirCarePlan([
            'person_id' => $this->person->id,
            'legal_entity_id' => $this->legalEntity->id,
            'author_id' => $this->employee->id,
            'description' => 'Опис чернетки',
            'inform_with' => $authMethod,
            'co_authors' => $contributors,
            'supporting_info' => ['episodes' => [['uuid' => $episodeUuid]], 'medical_records' => [$record]],
        ]);

        $editor = Livewire::test(CarePlanUpdate::class, ['legalEntity' => $this->legalEntity, 'carePlan' => $draft])
            ->call('$refresh')
            ->assertSnapshotSet('carePlanId', $draft->id)
            ->assertViewHas('carePlan', fn ($plan): bool => $draft->is($plan))
            ->assertSet('carePlanUuid', $draft->uuid)
            ->assertSet('form.description', 'Опис чернетки')
            ->assertSet('form.note', '')
            ->assertSet('form.informWith', $authMethod)
            ->assertSet('form.coAuthors', $contributors)
            ->assertSet('form.episodes.0.uuid', $episodeUuid)
            ->assertCount('form.episodes', 1)
            ->assertSet('form.medicalRecords', [$record]);

        $this->assertArrayNotHasKey('carePlan', $editor->snapshot['data']);
    }

    public function test_blocks_activity_cancel_when_open_medication_request_exists(): void
    {
        $this->actingAs($this->user);

        $carePlan = $this->createFhirCarePlan([
            'uuid' => (string) Str::uuid(),
            'person_id' => $this->person->id,
            'author_id' => $this->employee->id,
            'legal_entity_id' => $this->legalEntity->id,
            'period_start' => now()->format('Y-m-d'),
            'title' => 'Gate Activity Cancel',
            'status' => 'active',
        ]);
        $this->grantCarePlanWriteApproval($carePlan, $this->employee);

        $activity = $this->createFhirCarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'care_plan_id' => $carePlan->id,
            'status' => 'scheduled',
            'kind' => 'medication_request',
            'author_id' => $this->employee->id,
        ]);

        $basedOnId = Identifier::create(['value' => $activity->uuid])->id;
        $intentId = Coding::firstOrCreate([
            'code' => 'order',
            'system' => 'http://hl7.org/fhir/request-intent',
        ])->id;

        MedicationRequestRequest::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $this->employee->id,
            'person_id' => $this->person->id,
            'status' => 'active',
            'medication_id' => 'INN-101',
            'medication_qty' => 10,
            'intent_id' => $intentId,
            'based_on_id' => $basedOnId,
            'source' => MedicationRequestRequest::SOURCE_LOCAL,
        ]);

        Livewire::test(CarePlanShow::class, ['carePlan' => $carePlan])
            ->call('openSignatureModal', 'cancel_activity', $activity->id)
            ->assertSet('showSignatureModal', false)
            ->assertSee(__('care-plan.cannot_cancel_activity_open_docs', [
                'reasons' => __('care-plan.open_doc_type.medication_request').' ('.__('care-plan.status.active').')',
            ]));
    }

    public function test_blocks_activity_complete_when_open_service_request_exists(): void
    {
        $this->actingAs($this->user);

        $carePlan = $this->createFhirCarePlan([
            'uuid' => (string) Str::uuid(),
            'person_id' => $this->person->id,
            'author_id' => $this->employee->id,
            'legal_entity_id' => $this->legalEntity->id,
            'period_start' => now()->format('Y-m-d'),
            'title' => 'Gate Activity Complete',
            'status' => 'active',
        ]);
        $this->grantCarePlanWriteApproval($carePlan, $this->employee);

        $activity = $this->createFhirCarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'care_plan_id' => $carePlan->id,
            'status' => 'scheduled',
            'kind' => 'service_request',
            'author_id' => $this->employee->id,
        ]);

        $basedOnId = Identifier::create(['value' => $activity->uuid])->id;
        $intentId = Coding::firstOrCreate([
            'code' => 'order',
            'system' => 'http://hl7.org/fhir/request-intent',
        ])->id;
        $priorityId = CodeableConcept::firstOrCreate(['text' => 'routine'])->id;

        ServiceRequestRequest::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $this->employee->id,
            'person_id' => $this->person->id,
            'status' => 'in-progress',
            'service_id' => '59300-00',
            'quantity' => 1,
            'intent_id' => $intentId,
            'based_on_id' => $basedOnId,
            'priority_id' => $priorityId,
        ]);

        Livewire::test(CarePlanShow::class, ['carePlan' => $carePlan])
            ->call('openSignatureModal', 'complete_activity', $activity->id)
            ->assertSet('showSignatureModal', false)
            ->assertSee(__('care-plan.cannot_complete_activity_open_docs', [
                'reasons' => __('care-plan.open_doc_type.service_request').' ('.__('care-plan.status.in_progress').')',
            ]));
    }

    public function test_blocks_plan_cancel_when_activity_is_scheduled(): void
    {
        $this->actingAs($this->user);

        $carePlan = $this->createFhirCarePlan([
            'uuid' => (string) Str::uuid(),
            'person_id' => $this->person->id,
            'author_id' => $this->employee->id,
            'legal_entity_id' => $this->legalEntity->id,
            'period_start' => now()->format('Y-m-d'),
            'title' => 'Gate Plan Cancel',
            'status' => 'active',
        ]);
        $this->grantCarePlanWriteApproval($carePlan, $this->employee);

        $this->createFhirCarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'care_plan_id' => $carePlan->id,
            'status' => 'scheduled',
            'kind' => 'service_request',
            'author_id' => $this->employee->id,
        ]);

        Livewire::test(CarePlanShow::class, ['carePlan' => $carePlan->fresh('activities')])
            ->call('openSignatureModal', 'cancel')
            ->assertSet('showSignatureModal', false)
            ->assertSee(__('care-plan.cannot_cancel_plan_blocking_activities', ['statuses' => __('care-plan.status.scheduled')]))
            ->assertSeeHtml('role="alert"')
            ->assertNotDispatched('flashMessage');
    }

    public function test_allows_activity_cancel_modal_when_no_open_documents(): void
    {
        $this->actingAs($this->user);

        $carePlan = $this->createFhirCarePlan([
            'uuid' => (string) Str::uuid(),
            'person_id' => $this->person->id,
            'author_id' => $this->employee->id,
            'legal_entity_id' => $this->legalEntity->id,
            'period_start' => now()->format('Y-m-d'),
            'title' => 'Gate Activity Allow',
            'status' => 'active',
        ]);
        $this->grantCarePlanWriteApproval($carePlan, $this->employee);

        $activity = $this->createFhirCarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'care_plan_id' => $carePlan->id,
            'status' => 'scheduled',
            'kind' => 'medication_request',
            'author_id' => $this->employee->id,
        ]);

        $basedOnId = Identifier::create(['value' => $activity->uuid])->id;
        $intentId = Coding::firstOrCreate([
            'code' => 'order',
            'system' => 'http://hl7.org/fhir/request-intent',
        ])->id;

        MedicationRequestRequest::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $this->employee->id,
            'person_id' => $this->person->id,
            'status' => 'rejected',
            'medication_id' => 'INN-101',
            'medication_qty' => 10,
            'intent_id' => $intentId,
            'based_on_id' => $basedOnId,
            'source' => MedicationRequestRequest::SOURCE_LOCAL,
        ]);

        Livewire::test(CarePlanShow::class, ['carePlan' => $carePlan])
            ->call('openSignatureModal', 'cancel_activity', $activity->id)
            ->assertSet('showSignatureModal', true)
            ->assertSet('actionType', 'cancel_activity');
    }

    public function test_blocks_plan_complete_when_activity_is_in_progress_or_processed(): void
    {
        $this->actingAs($this->user);

        $carePlan = $this->createFhirCarePlan([
            'uuid' => (string) Str::uuid(),
            'person_id' => $this->person->id,
            'author_id' => $this->employee->id,
            'legal_entity_id' => $this->legalEntity->id,
            'period_start' => now()->format('Y-m-d'),
            'title' => 'Gate Plan Complete',
            'status' => 'active',
        ]);
        $this->grantCarePlanWriteApproval($carePlan, $this->employee);

        $this->createFhirCarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'care_plan_id' => $carePlan->id,
            'status' => 'processed',
            'kind' => 'service_request',
            'author_id' => $this->employee->id,
        ]);

        Livewire::test(CarePlanShow::class, ['carePlan' => $carePlan->fresh('activities')])
            ->assertSee(__('forms.synchronise_with_eHealth'))
            ->call('openSignatureModal', 'complete')
            ->assertSet('showSignatureModal', false)
            ->assertSeeHtml('role="alert"')
            ->assertNotDispatched('flashMessage');
    }

    public function test_allows_plan_complete_modal_when_all_activities_are_final_and_one_is_completed(): void
    {
        $this->actingAs($this->user);

        $carePlan = $this->createFhirCarePlan([
            'uuid' => (string) Str::uuid(),
            'person_id' => $this->person->id,
            'author_id' => $this->employee->id,
            'legal_entity_id' => $this->legalEntity->id,
            'period_start' => now()->format('Y-m-d'),
            'title' => 'Gate Plan Complete Ready',
            'status' => 'active',
        ]);
        $this->grantCarePlanWriteApproval($carePlan, $this->employee);

        $this->createFhirCarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'care_plan_id' => $carePlan->id,
            'status' => 'completed',
            'kind' => 'service_request',
            'author_id' => $this->employee->id,
        ]);

        Livewire::test(CarePlanShow::class, ['carePlan' => $carePlan->fresh('activities')])
            ->call('openSignatureModal', 'complete')
            ->assertSet('showSignatureModal', true)
            ->assertSet('actionType', 'complete');
    }
}

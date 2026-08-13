<?php

declare(strict_types=1);

namespace Tests\Feature\Encounter;

use App\Enums\Person\EncounterStatus;
use App\Livewire\Encounter\Concerns\ManagesEncounterEPrescription;
use App\Livewire\Encounter\Concerns\ManagesEncounterReferrals;
use App\Livewire\Encounter\Concerns\ResolvesEncounterStandaloneContext;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\Person\Person;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class EncounterStandalonePhase6Test extends TestCase
{
    use DatabaseTransactions;

    protected Person $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->person = Person::create([
            'uuid' => (string) Str::uuid(),
            'birth_date' => '1990-05-05',
            'gender' => 'MALE',
            'patient_signed' => true,
            'process_disclosure_data_consent' => true,
        ]);
    }

    public function test_referral_drawer_blocked_for_draft_encounter(): void
    {
        $encounter = $this->createEncounter(EncounterStatus::DRAFT->value);
        $harness = $this->makeHarness($encounter->id);

        $harness->openEncounterReferralDrawer();

        $this->assertFalse($harness->showEncounterReferralDrawer);
        $this->assertNotEmpty($harness->flashes);
    }

    public function test_eprescription_drawer_opens_for_finished_encounter(): void
    {
        $encounter = $this->createEncounter(EncounterStatus::FINISHED->value);
        $harness = $this->makeHarness($encounter->id);

        $harness->openEncounterEPrescriptionDrawer();

        $this->assertTrue($harness->showEncounterEPrescriptionDrawer);
        $this->assertSame('1', $harness->encounterEPrescriptionForm['medication_qty']);
    }

    public function test_referral_drawer_opens_for_finished_encounter(): void
    {
        $encounter = $this->createEncounter(EncounterStatus::FINISHED->value);
        $harness = $this->makeHarness($encounter->id);

        $harness->openEncounterReferralDrawer();

        $this->assertTrue($harness->showEncounterReferralDrawer);
        $this->assertSame('service_request', $harness->encounterReferralForm['kind']);
    }

    private function makeHarness(int $encounterId): EncounterStandaloneHarness
    {
        $harness = new EncounterStandaloneHarness();
        $harness->encounterId = $encounterId;

        return $harness;
    }

    private function createEncounter(string $status): Encounter
    {
        $identifierId = \App\Models\MedicalEvents\Sql\Identifier::create(['value' => (string) Str::uuid()])->id;
        $codingId = \App\Models\MedicalEvents\Sql\Coding::create([
            'code' => 'AMB',
            'system' => 'eHealth/encounter_classes',
        ])->id;
        $ccId = \App\Models\MedicalEvents\Sql\CodeableConcept::create()->id;

        return Encounter::create([
            'uuid' => (string) Str::uuid(),
            'person_id' => $this->person->id,
            'status' => $status,
            'episode_id' => $identifierId,
            'class_id' => $codingId,
            'type_id' => $ccId,
            'ehealth_inserted_at' => now(),
        ]);
    }
}

/**
 * Lightweight host for encounter standalone traits (avoids full EncounterEdit mount).
 */
class EncounterStandaloneHarness
{
    use ResolvesEncounterStandaloneContext;
    use ManagesEncounterEPrescription;
    use ManagesEncounterReferrals;

    public int $encounterId;

    public bool $showSignatureModal = false;

    public ?string $actionType = null;

    /** @var list<array{type?: string, message?: string}> */
    public array $flashes = [];

    public function dispatch(string $event, mixed ...$params): static
    {
        if ($event === 'flashMessage') {
            $payload = $params[0] ?? [];
            $this->flashes[] = is_array($payload) ? $payload : [];
        }

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Referral;

use App\Classes\eHealth\Middleware\CheckSessionToken;
use App\Http\Middleware\EnsureSingleSession;
use App\Livewire\Referral\EReferralEdit;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\Repository;
use App\Services\Dictionary\Dictionaries\BasicDictionary;
use App\Services\Dictionary\Dictionaries\MedicalProgramDictionary;
use App\Services\Dictionary\Dictionaries\ServiceDictionary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Composition\CompositionTestFixtures;
use Tests\Feature\Composition\UsesCompositionDictionaries;
use Tests\TestCase;

class EReferralEditTest extends TestCase
{
    use CompositionTestFixtures;
    use DatabaseTransactions;
    use UsesCompositionDictionaries;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->withoutMiddleware([EnsureSingleSession::class, CheckSessionToken::class]);
        $dictionaries = Cache::get(BasicDictionary::KEY, []);
        $dictionaries[] = ['name' => 'SERVICE_REQUEST_PRIORITY', 'values' => [
            ['code' => 'routine', 'description' => 'Планове', 'is_active' => true],
            ['code' => 'urgent', 'description' => 'Ургентне', 'is_active' => true],
            ['code' => 'retired', 'description' => 'Неактивне', 'is_active' => false],
        ]];
        Cache::forever(BasicDictionary::KEY, $dictionaries);
        foreach ([ServiceDictionary::KEY, MedicalProgramDictionary::KEY] as $key) {
            Cache::forever($key, []);
            Cache::forever($key.':fresh', true);
        }
    }

    public function test_author_can_open_the_registry_link_and_save_only_the_three_editable_fields(): void
    {
        $params = $this->draft();
        $record = $params['serviceRequest'];
        $repository = Repository::serviceRequest();
        $before = $repository->getResource($record);
        $editUrl = route('persons.referrals.edit', $params);
        $this->get(route('persons.referrals', [$params['legalEntity'], $params['person']]))->assertOk()->assertSee($editUrl, false);
        $this->get($editUrl)->assertOk()->assertSeeLivewire(EReferralEdit::class);

        Livewire::test(EReferralEdit::class, $params)
            ->assertSet('eReferralForm.note', $before['note'])
            ->assertSet('eReferralForm.patientInstruction', $before['patientInstruction'])
            ->assertSet('eReferralForm.priority', $before['priority'])
            ->set('eReferralForm.priority', 'urgent')
            ->set('eReferralForm.note', 'Уточнення лікаря')
            ->set('eReferralForm.patientInstruction', 'Прийти натще')
            ->call('save')->assertHasNoErrors()->assertSee(__('referrals.draft_saved'));

        $this->assertSame(array_replace($before, [
            'priority' => 'urgent', 'note' => 'Уточнення лікаря', 'patientInstruction' => 'Прийти натще',
        ]), $repository->getResource($record->fresh()));
        Http::assertNothingSent();
    }

    public function test_nullable_draft_opens_and_inactive_priority_cannot_be_saved(): void
    {
        $params = $this->draft(emptyFields: true);
        Livewire::test(EReferralEdit::class, $params)
            ->assertSet('eReferralForm.note', null)
            ->assertSet('eReferralForm.patientInstruction', null)
            ->assertSet('eReferralForm.priority', 'routine')
            ->set('eReferralForm.priority', 'retired')
            ->set('eReferralForm.note', 'Зміна')
            ->call('save')->assertHasErrors(['eReferralForm.priority']);
        $this->assertNull($params['serviceRequest']->fresh()->note);
        $this->assertNull($params['serviceRequest']->fresh()->priorityId);
    }

    public function test_other_patient_and_missing_write_permission_cannot_open_the_editor(): void
    {
        $params = $this->draft();
        $otherPerson = Person::create(['uuid' => (string) Str::uuid(), 'birth_date' => '1990-01-01', 'gender' => 'MALE']);
        $this->get(route('persons.referrals.edit', array_replace($params, ['person' => $otherPerson])))->assertNotFound();

        $params = $this->draft(scopes: ['patient_summary:read', 'service_request:read']);
        $editUrl = route('persons.referrals.edit', $params);
        $this->get($editUrl)->assertNotFound();
        $this->get(route('persons.referrals', [$params['legalEntity'], $params['person']]))->assertOk()->assertDontSee($editUrl, false);
        Http::assertNothingSent();
    }

    public function test_an_active_referral_or_another_authors_draft_cannot_be_edited(): void
    {
        $params = $this->draft();
        $record = $params['serviceRequest'];
        $record->update(['status' => 'active']);
        $this->get(route('persons.referrals.edit', $params))->assertNotFound();
        $record->update(['status' => 'draft', 'request_number' => '1234-5678']);
        $this->get(route('persons.referrals.edit', $params))->assertNotFound();
        $record->update(['status' => 'new', 'request_number' => null]);
        $this->get(route('persons.referrals.edit', $params))->assertOk();
        $record->employee->update(['is_active' => false]);
        $this->get(route('persons.referrals.edit', $params))->assertNotFound();

        $other = $this->compositionFixture(scopes: ['patient_summary:read', 'service_request:read', 'service_request:write']);
        $record->employee->update(['is_active' => true]);
        $this->assertFalse($other['user']->can('edit', [$record->fresh(), $other['legalEntity']]));
        $this->assertFalse($other['user']->can('edit', [$record->fresh(), $params['legalEntity']]));
        Http::assertNothingSent();
    }

    public function test_a_stale_editor_cannot_overwrite_changes_or_edit_during_submission(): void
    {
        $params = $this->draft();
        $record = $params['serviceRequest'];
        $editor = Livewire::test(EReferralEdit::class, $params);
        $record->update(['note' => 'Зміна в іншій вкладці']);
        $editor->set('eReferralForm.note', 'Застарілий текст')->call('save')->assertSee(__('referrals.messages.action_unavailable'));
        $this->assertSame('Зміна в іншій вкладці', $record->fresh()->note);

        $editor = Livewire::test(EReferralEdit::class, array_replace($params, ['serviceRequest' => $record->fresh()]));
        Repository::serviceRequest()->beginSubmission($record, ['status' => 'active']);
        $editor->set('eReferralForm.note', 'Текст після підписання')->call('save')->assertSee(__('referrals.messages.action_unavailable'));
        $this->get(route('persons.referrals.edit', $params))->assertNotFound();
        $this->assertSame('Зміна в іншій вкладці', $record->fresh()->note);
        Http::assertNothingSent();
    }

    protected function draft(
        bool $emptyFields = false,
        array $scopes = ['patient_summary:read', 'service_request:read', 'service_request:write']
    ): array {
        ['legalEntity' => $legalEntity, 'person' => $person, 'user' => $user, 'employee' => $employee] = $this->compositionFixture(scopes: $scopes);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user, 'ehealth');
        $this->withSession(['ehealth_legal_entity_uuid' => $legalEntity->uuid]);
        $resource = json_decode(file_get_contents(base_path('tests/Fixtures/ServiceRequest/resource.json')), true, flags: JSON_THROW_ON_ERROR);
        unset($resource['subject'], $resource['requisition']);
        $resource['id'] = (string) Str::uuid();
        $resource['status'] = 'draft';
        $resource['requesterEmployee']['identifier']['value'] = $employee->uuid;
        if ($emptyFields) {
            $resource = Arr::except($resource, ['note', 'patientInstruction', 'priority']);
        }
        $serviceRequest = Repository::serviceRequest()->storeResource($resource, $person->id, $employee->id);

        return compact('legalEntity', 'person', 'serviceRequest');
    }
}

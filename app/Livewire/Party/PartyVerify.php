<?php

declare(strict_types=1);

namespace App\Livewire\Party;

use App\Classes\eHealth\EHealth;
use App\Enums\Party\DracsDeathVerificationReason;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Models\LegalEntity;
use App\Models\Relations\Party;
use App\Services\Party\PartyVerificationCache;
use App\Traits\ProcessesPartyVerificationResponses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;
use UnexpectedValueException;

class PartyVerify extends Component
{
    use AuthorizesRequests;
    use ProcessesPartyVerificationResponses;

    public Party $party;
    public LegalEntity $legalEntity;

    #[Locked]
    public array $verificationDetails = [];

    public string $verificationStream = 'dracs_death';

    #[Locked]
    public bool $showUpdateModal = false;

    public bool $isSyncing = false;

    /**
     * Always VERIFIED for DRACS death updates (API allows only this status).
     */
    public string $status = 'VERIFIED';

    public string $reason = '';

    public string $comment = '';
    public string $backUrl = '';

    public function mount(LegalEntity $legalEntity, Party $party): void
    {
        $this->legalEntity = $legalEntity;
        $this->party = $party;
        $this->loadVerificationDetails();
        $this->status = 'VERIFIED';

        $previous = url()->previous();
        $current = request()->url();

        if ($previous !== $current && str_contains($previous, '/party-verification')) {
            $this->backUrl = $previous;
        } else {
            $this->backUrl = route('party.verification.index', ['legalEntity' => $legalEntity->id]);
        }
    }

    public function updatedReason(string $value): void
    {
        $normalized = DracsDeathVerificationReason::tryFromLegacy($value);
        if ($normalized !== null && $normalized->value !== $value) {
            $this->reason = $normalized->value;
        }
    }

    /**
     * API allows update only when current dracs_death status is NOT_VERIFIED.
     */
    #[Computed]
    public function canUpdateVerification(): bool
    {
        $deathStatus = data_get($this->verificationDetails, 'details.dracs_death.verification_status');

        return $deathStatus === 'NOT_VERIFIED';
    }

    /**
     * Loads and filters verification details for the party from the eHealth API.
     *
     * @return void
     */
    public function loadVerificationDetails(): void
    {
        if (Auth::user()->cannot('viewVerification', $this->party) || !$this->party->uuid) {
            return;
        }

        unset($this->canUpdateVerification);

        try {
            $response = EHealth::party()->getDetails($this->party->uuid);
            $this->verificationDetails = $response->validate();
            PartyVerificationCache::put($this->party->uuid, $this->verificationDetails);
        } catch (Throwable) {
            Log::warning('Could not load party verification details', ['party_id' => $this->party->id]);
            $this->verificationDetails = [];
        }
    }

    /**
     * Force-sync one party verification via GET /api/parties/{uuid}/verification.
     * Same pattern as EmployeeRequestIndex::syncOne / PartyVerify::updateStatus:
     * perform eHealth call, persist, then reload UI via loadVerificationDetails().
     */
    public function syncOne(): void
    {
        $this->authorize('syncVerification', Party::class);

        if ($this->isSyncing) {
            return;
        }

        if (!$this->party->uuid) {
            $this->dispatch('flashMessage', [
                'message' => __('party_verification.messages.sync_one_missing_uuid'),
                'type' => 'error',
            ]);

            return;
        }

        $this->isSyncing = true;

        try {
            Log::info('[PartyVerify SyncOne] Started', [
                'party_uuid' => $this->party->uuid,
                'legal_entity_id' => $this->legalEntity->id,
            ]);

            $response = EHealth::party()->getDetails($this->party->uuid);

            $data = $response->validate();
            $this->processPartyVerificationDetail($this->party->uuid, $data, $this->legalEntity);
            PartyVerificationCache::put($this->party->uuid, $data);

            $this->loadVerificationDetails();
            $this->party->refresh();

            $this->dispatch('flashMessage', [
                'message' => __('party_verification.messages.sync_one_success'),
                'type' => 'success',
            ]);
        } catch (Throwable $e) {
            Log::error('[PartyVerify SyncOne] ERROR: ' . $e->getMessage(), [
                'party_uuid' => $this->party->uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('flashMessage', [
                'message' => __('party_verification.messages.sync_one_failed', [
                    'error' => $e->getMessage(),
                ]),
                'type' => 'error',
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function checkAndOpenModal(): void
    {
        if (!$this->canUpdateVerification) {
            $this->dispatch('flashMessage', [
                'message' => __('party_verification.update_unavailable_reason'),
                'type' => 'error',
            ]);

            return;
        }

        $this->status = 'VERIFIED';
        $this->verificationStream = 'dracs_death';
        $this->reason = '';
        $this->comment = '';
        $this->resetErrorBag();
        $this->showUpdateModal = true;
    }

    public function closeUpdateModal(): void
    {
        $this->showUpdateModal = false;
        $this->reset(['reason', 'comment']);
        $this->status = 'VERIFIED';
        $this->resetErrorBag();
    }

    public function updateStatus(): void
    {
        if (Auth::user()->cannot('updateVerification', $this->party) || !$this->party->uuid) {
            Session::flash('error', __('party_verification.messages.update_failed'));

            return;
        }

        $this->status = 'VERIFIED';
        $this->verificationStream = 'dracs_death';

        $normalizedReason = DracsDeathVerificationReason::tryFromLegacy($this->reason);
        if ($normalizedReason !== null) {
            $this->reason = $normalizedReason->value;
        }

        $this->validate([
            'verificationStream' => 'required|string|in:dracs_death',
            'status' => 'required|string|in:VERIFIED',
            'reason' => ['required', 'string', Rule::enum(DracsDeathVerificationReason::class)],
            'comment' => 'nullable|string|max:3000',
        ]);

        // Check the current verification before sending a manual decision.
        $this->loadVerificationDetails();
        if (!$this->canUpdateVerification) {
            Session::flash('error', __('party_verification.update_unavailable_reason'));

            return;
        }

        try {
            $payload = [
                'dracs_death' => array_filter([
                    'verification_status' => 'VERIFIED',
                    'verification_reason' => $this->reason,
                    'verification_comment' => filled($this->comment) ? $this->comment : null,
                ], fn ($value): bool => $value !== null),
            ];

            $data = EHealth::party()->update($this->party->uuid, $payload)->validate();
            $death = $data['details']['dracs_death'] ?? [];
            if (($death['verification_status'] ?? null) !== 'VERIFIED'
                || ($death['verification_reason'] ?? null) !== $this->reason) {
                throw new UnexpectedValueException('eHealth did not confirm the requested death verification decision.');
            }

            $this->party->update(['verification_status' => $data['verification_status']]);
            PartyVerificationCache::put($this->party->uuid, $data);
            $this->verificationDetails = $data;
            unset($this->canUpdateVerification);

            $this->closeUpdateModal();

            Session::flash('success', __('party_verification.messages.update_success'));

        } catch (EHealthValidationException $e) {
            Log::error('[PARTY UPDATE VALIDATION ERROR]', [
                'party_uuid' => $this->party->uuid,
                'message' => $e->getMessage(),
                'details' => $e->getDetails(),
                'enum_values' => data_get($e->getDetails(), 'error.invalid.0.rules.0.params.values'),
            ]);

            Session::flash('error', __('party_verification.messages.update_failed_with_detail', [
                'message' => $e->getTranslatedMessage(),
            ]));

        } catch (EHealthResponseException $e) {
            Log::error('[PARTY UPDATE ERROR]', [
                'party_uuid' => $this->party->uuid,
                'message' => $e->getMessage(),
                'details' => $e->getDetails(),
            ]);

            $message = $e->isPartyNotVerified()
                ? __('errors.ehealth.messages.party_not_verified')
                : __('party_verification.messages.update_failed');

            Session::flash('error', $message);

        } catch (Throwable $e) {
            Log::error('[PARTY UPDATE SYSTEM ERROR]', [
                'party_uuid' => $this->party->uuid,
                'message' => $e->getMessage(),
            ]);

            Session::flash('error', __('party_verification.messages.update_failed_technical'));
        }
    }

    public function render()
    {
        return view('livewire.party.party-verify');
    }
}

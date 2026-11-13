<x-layouts.patient
    :personId="$personId"
    :patientFullName="$this->patientFullName"
    :showLegacyMessages="false"
    activeTab="referrals"
>
    <livewire:components.x-message :consume-messages="true" :key="(string) str()->uuid()" />
    @if ($this->summary !== [])
        <div class="breadcrumb-form shift-content p-4">
            <div class="record-inner-card">
                <dl class="record-inner-grid-container">
                    <div>
                        <dt class="record-inner-label">
                            {{ __('referrals.requisition') }}
                        </dt>
                        <dd class="record-inner-value">
                            {{ $this->summary['requestNumber'] ?: $this->summary['uuid'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="record-inner-label">
                            {{ __('forms.service') }}
                        </dt>
                        <dd class="record-inner-value">
                            {{ $this->summary['itemName'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="record-inner-label">
                            {{ __('forms.status.label') }}
                        </dt>
                        <dd class="record-inner-value">
                            {{ $this->summary['statusLabel'] }}
                        </dd>
                    </div>
                </dl>
                <form
                    @if ($this->isEditing) wire:submit="save" @endif
                    class="space-y-4 p-4"
                >
                    <div class="form-group">
                        <label for="referralPriority" class="label">
                            {{ __('referrals.table.priority') }}
                        </label>
                        <select
                            id="referralPriority"
                            wire:model="eReferralForm.priority"
                            class="input-select"
                            @disabled(!$this->isEditing)
                        >
                            @foreach ($eReferralForm->priorityOptions() as $code => $label)
                                <option value="{{ $code }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('eReferralForm.priority')
                            <p class="text-error">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                    @foreach (['note' => 'note', 'patientInstruction' => 'patient_instruction'] as $field => $label)
                        <div class="form-group">
                            <label for="referral-{{ $field }}" class="label">
                                {{ __('referrals.registry.'.$label) }}
                            </label>
                            <textarea
                                id="referral-{{ $field }}"
                                wire:model="eReferralForm.{{ $field }}"
                                class="input"
                                @readonly(!$this->isEditing)
                            ></textarea>
                            @error('eReferralForm.'.$field)
                                <p class="text-error">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    @endforeach
                    <div class="flex gap-4">
                        <button type="button" x-on:click="$dispatch('close-referral')" class="button-primary-outline">
                            {{ __('forms.back') }}
                        </button>
                        @if ($this->isEditing)
                            <button
                                type="submit"
                                class="button-primary"
                                wire:loading.attr="disabled"
                                wire:target="save"
                            >
                                {{ __('forms.save') }}
                            </button>
                        @elseif ($this->canEdit)
                            <button type="button" wire:click="edit" class="button-primary">
                                {{ __('forms.edit') }}
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-layouts.patient>

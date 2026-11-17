<x-layouts.patient
    :personId="$personId"
    :patientFullName="$patientFullName"
    :showLegacyMessages="false"
    activeTab="referrals"
>
    <livewire:components.x-message :consume-messages="true" :key="(string) str()->uuid()" />
    <div class="breadcrumb-form shift-content space-y-4 p-4">
        @include('livewire.referral.parts.summary')
        @include('livewire.referral.parts.fhir-details')
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('persons.referrals', [legalEntity(), 'person' => $personId]) }}" class="button-primary-outline">
                {{ __('forms.back') }}
            </a>
            @can('edit', $referral)
                <a href="{{ route('persons.referrals.edit', [legalEntity(), 'person' => $personId, 'serviceRequest' => $referralId]) }}" class="button-primary">
                    {{ __('forms.edit') }}
                </a>
            @endcan
        </div>
    </div>
</x-layouts.patient>

<x-layouts.patient
    :personId="$personId"
    :patientFullName="$patientFullName"
    :showLegacyMessages="false"
    activeTab="referrals"
>
    <livewire:components.x-message :consume-messages="true" :key="(string) str()->uuid()" />
    <div class="breadcrumb-form shift-content space-y-4 p-4">
        <h1 class="text-lg font-semibold">{{ __('referrals.document_title') }}</h1>
        <div class="record-inner-card">
            <dl class="record-inner-grid-container">
                @foreach ([
                    'requestNumber' => __('referrals.requisition'),
                    'uuid' => __('referrals.registry.document_id'),
                    'itemName' => __('forms.service'),
                    'statusLabel' => __('forms.status.label'),
                    'periodLabel' => __('referrals.period'),
                    'categoryLabel' => __('referrals.category'),
                    'priorityLabel' => __('referrals.table.priority'),
                    'quantity' => __('care-plan.quantity'),
                    'programName' => __('care-plan.program'),
                    'note' => __('referrals.registry.note'),
                    'patientInstruction' => __('referrals.registry.patient_instruction'),
                ] as $field => $label)
                    @continue($field === 'quantity' && isset($resource['quantity']))
                    <div>
                        <dt class="record-inner-label">
                            {{ $label }}
                        </dt>
                        <dd class="record-inner-value whitespace-pre-line break-words">{{ filled($summary[$field] ?? null) ? $summary[$field] : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        @if ($referral->detailsSyncedAt !== null)
            @include('livewire.referral.parts.fhir-details')
        @else
            <p>{{ __('referrals.only_summary_available') }}</p>
        @endif
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('persons.referrals', [$legalEntity, 'person' => $personId]) }}" class="button-primary-outline">
                {{ __('forms.back') }}
            </a>
            @can('edit', [$referral, $legalEntity])
                <a href="{{ route('persons.referrals.edit', [$legalEntity, 'person' => $personId, 'serviceRequest' => $referralId]) }}" class="button-primary">
                    {{ __('forms.edit') }}
                </a>
            @endcan
        </div>
    </div>
</x-layouts.patient>

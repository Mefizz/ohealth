<div class="record-inner-card">
    <dl class="record-inner-grid-container">
        @foreach ([
            'code' => 'service_reference',
            'program' => 'program_reference',
            'context' => 'context',
            'requesterEmployee' => 'requester_employee',
            'requesterLegalEntity' => 'requester_legal_entity',
            'performer' => 'performer',
            'locationReference' => 'location_reference',
        ] as $field => $label)
            @if (filled($resource[$field] ?? null))
                <div>
                    <dt class="record-inner-label">
                        {{ __('referrals.fhir.'.$label) }}
                    </dt>
                    <dd class="record-inner-value break-words">
                        {{ filled(data_get($resource, $field.'.displayValue')) ? data_get($resource, $field.'.displayValue') : data_get($resource, $field.'.identifier.value', '—') }}
                    </dd>
                </div>
            @endif
        @endforeach
        @foreach ([
            'authoredOn' => 'authored_on',
            'occurrencePeriod.start' => 'occurrence_start',
            'occurrencePeriod.end' => 'occurrence_end',
            'occurrenceDateTime' => 'occurrence_date_time',
            'explanatoryLetter' => 'explanatory_letter',
            'programProcessingStatus' => 'program_processing_status',
        ] as $field => $label)
            @if (filled(data_get($resource, $field)))
                <div>
                    <dt class="record-inner-label">
                        {{ __('referrals.fhir.'.$label) }}
                    </dt>
                    <dd class="record-inner-value whitespace-pre-line break-words">
                        {{ data_get($resource, $field) }}
                    </dd>
                </div>
            @endif
        @endforeach
        @foreach (['performerType' => 'performer_type', 'statusReason' => 'status_reason'] as $field => $label)
            @if (!empty($resource[$field]))
                <div>
                    <dt class="record-inner-label">
                        {{ __('referrals.fhir.'.$label) }}
                    </dt>
                    <dd class="record-inner-value break-words">
                        {{ data_get($resource, $field.'.text') ?: data_get($resource, $field.'.coding.0.code', '—') }}
                    </dd>
                </div>
            @endif
        @endforeach
        @foreach (['quantity' => 'quantity', 'remainingQuantity' => 'remaining_quantity'] as $field => $label)
            @if (isset($resource[$field]))
                <div>
                    <dt class="record-inner-label">
                        {{ __('referrals.fhir.'.$label) }}
                    </dt>
                    <dd class="record-inner-value break-words">
                        {{ data_get($resource, $field.'.comparator') }}
                        {{ data_get($resource, $field.'.value') }}
                        {{ data_get($resource, $field.'.unit') ?: data_get($resource, $field.'.code') }}
                    </dd>
                </div>
            @endif
        @endforeach
        @foreach ([
            'basedOn' => 'based_on',
            'supportingInfo' => 'supporting_info',
            'reasonReference' => 'reason_reference',
            'permittedResources' => 'permitted_resources',
            'specimens' => 'specimens',
            'focus' => 'focus',
        ] as $field => $label)
            @if (!empty($resource[$field]))
                <div>
                    <dt class="record-inner-label">
                        {{ __('referrals.fhir.'.$label) }}
                    </dt>
                    <dd class="record-inner-value">
                        <ul class="space-y-2">
                            @foreach ($resource[$field] as $reference)
                                <li class="break-words">
                                    {{ data_get($reference, 'identifier.type.coding.0.code') }}
                                    {{ filled($reference['displayValue'] ?? null) ? $reference['displayValue'] : data_get($reference, 'identifier.value', '—') }}
                                </li>
                            @endforeach
                        </ul>
                    </dd>
                </div>
            @endif
        @endforeach
    </dl>
</div>

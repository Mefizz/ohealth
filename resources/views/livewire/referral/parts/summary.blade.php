@if ($summary['submissionPending'])
    <p role="status" class="p-4">
        {{ __('care-plan.submission_pending') }}
    </p>
@endif
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
            <div>
                <dt class="record-inner-label">
                    {{ $label }}
                </dt>
                <dd class="record-inner-value whitespace-pre-wrap break-words">
                    {{ filled($summary[$field] ?? null) ? $summary[$field] : '—' }}
                </dd>
            </div>
        @endforeach
    </dl>
</div>

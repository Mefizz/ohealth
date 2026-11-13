<?php

declare(strict_types=1);

return [
    'messages' => [
        'draft_changed' => 'This referral changed. Reload the page and try again.',
    ],
    'registry' => [
        'period_from' => 'from :date',
    ],
    'errors' => [
        'employee_required' => 'No employee of the current facility was found for this action.',
        'quantity_invalid' => 'Quantity must be a finite number.',
        'quantity_precision' => 'Quantity cannot be represented without loss in the JSON numeric contract.',
        'occurrence_conflict' => 'Only one occurrence variant is allowed.',
        'reference_incomplete' => 'A reference requires an explicit resource type and value.',
        'identity_required' => 'A mapped Service Request requires its identity.',
        'identity_and_service_required' => 'A complete Service Request requires its identity and service reference.',
        'import_patient_mismatch' => 'The Service Request patient does not match its import context.',
        'different_patient' => 'The Service Request belongs to another patient.',
        'full_import_or_conversion_required' => 'Service Request requires full import or explicit draft conversion before use.',
        'draft_changed' => 'The draft changed after preflight; repeat reconciliation.',
        'historical_import_required' => 'Registered historical resources require a complete remote import.',
        'historical_author_unknown' => 'Historical author identity cannot be recovered.',
        'historical_encounter_unknown' => 'Historical context is untyped and cannot be matched to a patient encounter.',
        'historical_activity_mismatch' => 'The historical activity reference cannot be matched to this patient.',
        'historical_units_missing' => 'Historical quantity units are missing; reconciliation is required.',
        'historical_service_type_unknown' => 'Historical service/service_group type cannot be recovered.',
        'activity_patient_mismatch' => 'The activity belongs to another patient.',
        'uuid_patient_mismatch' => 'A Service Request UUID already belongs to another patient.',
        'submission_mismatch' => 'The processed Service Request does not match the submitted record.',
    ],
];

<?php

declare(strict_types=1);

namespace App\Contracts;

interface FhirMapperContract
{
    /**
     * Convert a flat form array to a FHIR structure for persistence/API.
     *
     * $data is validated Livewire form data (flat key-value pairs), for example:
     * <code>
     * [
     *   'classCode'   => 'AMB',
     *   'typeCode'    => 'OUTPATIENT',
     *   'periodDate'  => '2024-01-01',
     *   'periodStart' => '08:00',
     *   'periodEnd'   => '08:30',
     * ]
     * </code>
     *
     * @param  array  $data  Flat form data (validated Livewire input)
     * @param  mixed  ...$context  Additional context (e.g. shared UUIDs, pre-built FHIR sub-resources)
     * @return array FHIR-structured array ready for DB persistence or eHealth API submission
     */
    public function toFhir(array $data, mixed ...$context): array;

    /**
     * Convert a FHIR structure (from DB/API) to a flat form array.
     *
     * $data is a nested FHIR resource as stored in the DB or returned by the eHealth API, for example:
     * <code>
     * [
     *   'class'  => ['code' => 'AMB', 'system' => 'eHealth/encounter_classes'],
     *   'period' => ['start' => '2024-01-01T08:00:00+02:00', 'end' => '2024-01-01T08:30:00+02:00'],
     *   'type'   => ['coding' => [['code' => 'OUTPATIENT', 'system' => 'eHealth/encounter_types']]],
     * ]
     * </code>
     *
     * @param  array  $data  FHIR resource data (from DB or eHealth API)
     * @param  mixed  ...$context  Additional context (e.g. detail maps for related resources)
     * @return array Flat key-value array suitable for populating a Livewire form
     */
    public function fromFhir(array $data, mixed ...$context): array;
}

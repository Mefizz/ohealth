<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents\Mappers;

use App\Contracts\FhirMapperContract;
use App\Enums\Person\EncounterStatus;
use App\Services\MedicalEvents\FhirResource;
use Carbon\CarbonImmutable;
use Carbon\Carbon;

class EncounterMapper implements FhirMapperContract
{
    /**
     * Build a FHIR encounter structure ready for the repository or eHealth API.
     *
     * @param  array  $data  Flat encounter form data
     * @param  mixed  ...$context  [0] array $fhirConditions  Already-mapped FHIR conditions, [1] array $uuids
     * @return array
     */
    public function toFhir(array $data, mixed ...$context): array
    {
        [$fhirConditions, $uuids] = $context;

        $result = [
            'id' => $data['uuid'] ?? $uuids['encounter'],
            'status' => EncounterStatus::FINISHED->value,
            'period' => [
                'start' => convertToEHealthISO8601($data['periodDate'] . ' ' . $data['periodStart']),
                'end' => convertToEHealthISO8601($data['periodDate'] . ' ' . $data['periodEnd'])
            ],
            'visit' => FhirResource::make()->coding('eHealth/resources', 'visit')->toIdentifier($uuids['visit']),
            'episode' => FhirResource::make()->coding('eHealth/resources', 'episode')->toIdentifier($uuids['episode']),
            'class' => FhirResource::make()->coding('eHealth/encounter_classes', $data['classCode'])->toCoding(),
            'type' => FhirResource::make()->coding('eHealth/encounter_types', $data['typeCode'])
                ->toCodeableConcept(),
            'performer' => FhirResource::make()->coding('eHealth/resources', 'employee')
                ->toIdentifier($uuids['employee'])
        ];

        if (($encounter['referralType'] ?? '') === 'electronic' && !empty($encounter['referralNumber'])) {
            $data['incomingReferral'] = FhirResource::make()
                ->coding('eHealth/resources', 'service_request')
                ->toIdentifier($encounter['referralNumber']);
        }

        if (($encounter['referralType'] ?? '') === 'paper' && !empty($encounter['paperReferral'])) {
            $paperReferral = $encounter['paperReferral'];

            $serviceRequestDate = $paperReferral['serviceRequestDate'] ?? null;

            if (!empty($serviceRequestDate)) {
                $serviceRequestDate = Carbon::createFromFormat('d.m.Y', $serviceRequestDate)->format('Y-m-d');
            }

            $data['paperReferral'] = array_filter([
                'requisition' => $paperReferral['requisition'] ?? null,
                'requesterLegalEntityName' => $paperReferral['requesterLegalEntityName'] ?? null,
                'requesterLegalEntityEdrpou' => $paperReferral['requesterLegalEntityEdrpou'] ?? null,
                'requesterEmployeeName' => $paperReferral['requesterEmployeeName'] ?? null,
                'serviceRequestDate' => $serviceRequestDate,
                'note' => $paperReferral['note'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        if (!empty($data['priorityCode'])) {
            $result['priority'] = FhirResource::make()->coding('eHealth/encounter_priority', $data['priorityCode'])
                ->toCodeableConcept();
        }

        if (!empty($data['reasons'])) {
            $result['reasons'] = collect($data['reasons'])
                ->map(fn (array $cc) => FhirResource::make()->coding('eHealth/ICPC2/reasons', $cc['code'])
                    ->toCodeableConcept())
                ->toArray();
        }

        $result['diagnoses'] = array_map(
            static function (array $fhir, array $diagnosis) {
                $item = [
                    'condition' => FhirResource::make()->coding('eHealth/resources', 'condition')
                        ->toIdentifier($fhir['id']),
                    'role' => FhirResource::make()->coding('eHealth/diagnosis_roles', $diagnosis['roleCode'])
                        ->toCodeableConcept(),
                ];

                if (!empty($diagnosis['rank'])) {
                    $item['rank'] = $diagnosis['rank'];
                }

                return $item;
            },
            $fhirConditions,
            $data['diagnoses']
        );

        if (!empty($data['actions'])) {
            $result['actions'] = collect($data['actions'])
                ->map(fn (array $cc) => FhirResource::make()->coding('eHealth/ICPC2/actions', $cc['code'])
                    ->toCodeableConcept())
                ->toArray();
        }

        // todo: action_references

        if (!empty($data['divisionId'])) {
            $result['division'] = FhirResource::make()->coding('eHealth/resources', 'division')
                ->toIdentifier($data['divisionId']);
        }

        // todo: prescriptions

        // todo: supporting_info

        // todo: hospitalization

        // todo: participant

        return $result;
    }

    /**
     * Populate flat form keys from a nested FHIR encounter. Used when loading an existing encounter for editing.
     *
     * @param  array  $data  FHIR encounter data
     * @return array
     */
    public function fromFhir(array $data, mixed ...$context): array
    {
        $hasIncomingReferral = !empty(data_get($encounter, 'incomingReferral.identifier.value'));
        $hasPaperReferral = !empty(data_get($encounter, 'paperReferral'));

        return [
            'classCode' => data_get($data, 'class.code'),
            'typeCode' => data_get($data, 'type.coding.0.code'),
            'divisionId' => data_get($data, 'division.identifier.value', ''),
            'priorityCode' => data_get($data, 'priority.coding.0.code', ''),
            'periodDate' => CarbonImmutable::parse(data_get($data, 'period.start'))->format('Y-m-d'),
            'periodStart' => CarbonImmutable::parse(data_get($data, 'period.start'))->format('H:i'),
            'periodEnd' => CarbonImmutable::parse(data_get($data, 'period.end'))->format('H:i'),
            'actions' => collect(data_get($data, 'actions', []))
                ->map(fn (array $action) => [
                    'code' => data_get($action, 'coding.0.code', ''),
                    'text' => data_get($action, 'text', '')
                ])
                ->toArray(),
            'reasons' => collect(data_get($data, 'reasons', []))
                ->map(fn (array $reason) => [
                    'code' => data_get($reason, 'coding.0.code', ''),
                    'text' => data_get($reason, 'text', '')
                ])
                ->toArray(),
            'diagnoses' => collect(data_get($data, 'diagnoses', []))
                ->map(fn (array $diagnosis) => [
                    'roleCode' => data_get($diagnosis, 'role.coding.0.code', ''),
                    'rank' => data_get($diagnosis, 'rank', '')
                ])
                ->toArray(),
            'referralType' => match (true) {
                $hasIncomingReferral => 'electronic',
                $hasPaperReferral => 'paper',
                default => ''
            },
            'referralNumber' => data_get($encounter, 'incomingReferral.identifier.value', ''),
            'paperReferral' => [
                'requisition' => data_get($encounter, 'paperReferral.requisition', ''),
                'requesterEmployeeName' => data_get($encounter, 'paperReferral.requesterEmployeeName', ''),
                'requesterLegalEntityEdrpou' => data_get($encounter, 'paperReferral.requesterLegalEntityEdrpou', ''),
                'requesterLegalEntityName' => data_get($encounter, 'paperReferral.requesterLegalEntityName', ''),
                'serviceRequestDate' => data_get($encounter, 'paperReferral.serviceRequestDate', ''),
                'note' => data_get($encounter, 'paperReferral.note', '')
            ]
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents\Mappers;

use App\Services\MedicalEvents\FhirResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ConditionMapper
{
    /**
     * Convert a flat form condition to a FHIR structure for persistence/API.
     *
     * @param  array  $condition
     * @param  array  $uuids
     * @return array
     */
    public function toFhir(array $condition, array $uuids): array
    {
        // Required params
        $data = [
            'id' => $condition['uuid'] ?? Str::uuid()->toString(),
            'primarySource' => $condition['primarySource'],
            'context' => FhirResource::make()->coding('eHealth/resources', 'encounter')->toIdentifier($uuids['encounter']),
            'code' => FhirResource::make()->coding($condition['codeSystem'], $condition['codeCode'])->toCodeableConcept(),
            'clinicalStatus' => $condition['clinicalStatus'],
            'verificationStatus' => $condition['verificationStatus'],
            'onsetDate' => convertToEHealthISO8601($condition['onsetDate'] . ' ' . $condition['onsetTime']),
        ];

        if ($condition['primarySource']) {
            $data['asserter'] = FhirResource::make()
                ->coding('eHealth/resources', 'employee')
                ->toIdentifier($uuids['employee'], $condition['asserterText'] ?? '');
        } else {
            $data['reportOrigin'] = FhirResource::make()
                ->coding('eHealth/report_origins', $condition['reportOriginCode'])
                ->toCodeableConcept();
        }

        if (!empty($condition['severityCode'])) {
            $data['severity'] = FhirResource::make()
                ->coding('eHealth/condition_severities', $condition['severityCode'])
                ->toCodeableConcept();
        }

        // todo: add  bodySites.*.code check

        if (!empty($condition['assertedDate']) && !empty($condition['assertedTime'])) {
            $data['assertedDate'] = convertToEHealthISO8601(
                $condition['assertedDate'] . ' ' . $condition['assertedTime']
            );
        }

        // todo: add stage

        $evidence = [];

        if (!empty($condition['evidenceCodes'])) {
            $evidence['codes'] = collect($condition['evidenceCodes'])
                ->map(
                    fn (array $cc) => FhirResource::make()
                        ->coding($cc['system'] ?? 'eHealth/ICPC2/reasons', $cc['code'])
                        ->toCodeableConcept()
                )
                ->values()
                ->toArray();
        }

        if (!empty($condition['evidenceDetails'])) {
            $evidence['details'] = collect($condition['evidenceDetails'])
                ->map(
                    fn (array $detail) => FhirResource::make()
                        ->coding('eHealth/resources', $detail['type'])
                        ->toIdentifier($detail['id'])
                )
                ->values()
                ->toArray();
        }

        if (!empty($evidence)) {
            $data['evidences'] = [$evidence];
        }

        return $data;
    }

    /**
     * Convert a FHIR condition (from DB) to a flat form structure.
     *
     * @param  array  $condition
     * @param  array  $detailsMap  UUID => [insertedAt, codeCode] for evidence details
     * @return array
     */
    public function fromFhir(array $condition, array $detailsMap = []): array
    {
        return [
            'uuid' => data_get($condition, 'uuid'),
            'primarySource' => data_get($condition, 'primarySource'),
            'codeSystem' => data_get($condition, 'code.coding.0.system'),
            'codeCode' => data_get($condition, 'code.coding.0.code'),
            'clinicalStatus' => data_get($condition, 'clinicalStatus'),
            'verificationStatus' => data_get($condition, 'verificationStatus'),
            'onsetDate' => CarbonImmutable::parse(data_get($condition, 'onsetDate'))->format('Y-m-d'),
            'onsetTime' => CarbonImmutable::parse(data_get($condition, 'onsetDate'))->format('H:i'),
            'assertedDate' => data_get($condition, 'assertedDate')
                ? CarbonImmutable::parse($condition['assertedDate'])->format('Y-m-d')
                : null,
            'assertedTime' => data_get($condition, 'assertedDate')
                ? CarbonImmutable::parse($condition['assertedDate'])->format('H:i')
                : null,
            'severityCode' => data_get($condition, 'severity.coding.0.code', ''),
            'asserterText' => data_get($condition, 'asserter.identifier.type.text', ''),
            'reportOriginCode' => data_get($condition, 'reportOrigin.coding.0.code', ''),
            'evidenceCodes' => collect(data_get($condition, 'evidences.0.codes', []))
                ->map(fn (array $code) => [
                    'code' => data_get($code, 'coding.0.code', ''),
                    'system' => data_get($code, 'coding.0.system', 'eHealth/ICPC2/reasons')
                ])
                ->toArray(),
            'evidenceDetails' => collect(data_get($condition, 'evidences.0.details', []))
                ->map(function (array $detail) use ($detailsMap) {
                    $uuid = data_get($detail, 'identifier.value');

                    return [
                        'id' => $uuid,
                        'insertedAt' => $detailsMap[$uuid]['insertedAt'] ?? '',
                        'codeCode' => $detailsMap[$uuid]['codeCode'] ?? '',
                        'type' => $detailsMap[$uuid]['type'] ?? ''
                    ];
                })
                ->toArray()
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Dto\DetectedIssue\Ehealth as DetectedIssueEhealth;
use App\Dto\Device\Ehealth as DeviceEhealth;
use App\Dto\DeviceAssociation\Ehealth as DeviceAssociationEhealth;
use App\Dto\DeviceDispense\Ehealth as DeviceDispenseEhealth;
use App\Dto\FormCollection;
use App\Dto\Specimen\Ehealth as SpecimenEhealth;
use App\Dto\Procedure\Ehealth as ProcedureEhealth;
use App\Enums\DeviceAssociation\Status as DeviceAssociationStatus;
use App\Enums\Episode\Status;
use App\Enums\Person\ConditionClinicalStatus;
use App\Enums\Person\DiagnosticReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class EncounterPackageBuilder
{
    /**
     * Build FHIR encounter package with optional episode.
     *
     * @param  array  $data  Validated form data
     * @param  string  $episodeType  'new' or 'existing'
     * @param  Status  $episodeStatus  Status to assign to the episode
     * @return array
     */
    public function build(array $data, string $episodeType, Status $episodeStatus = Status::ACTIVE): array
    {
        $uuids = [
            'encounter' => Str::uuid()->toString(),
            'visit' => Str::uuid()->toString(),
            'employee' => Auth::user()->getEncounterWriterEmployee($data['encounter']['classCode'])->uuid,
            'episode' => $data['episode']['id'] ?: Str::uuid()->toString()
        ];

        $package = $this->toFhir($data, $uuids);

        if ($episodeType === 'new') {
            $package['episode'] = Fhir::episode()->toFhir(
                $data['episode'],
                $uuids,
                $data['encounter']['periodDate'],
                $data['encounter']['periodStart'],
                $episodeStatus
            );
        }

        return array_filter($package);
    }

    /**
     * Map flat form data to a FHIR encounter package using the provided UUIDs.
     *
     * @param  array  $data  Validated form data (encounter, conditions, immunizations, etc.)
     * @param  array  $uuids  Shared UUIDs (encounter, visit, employee, episode)
     * @return array
     */
    public function toFhir(array $data, array $uuids): array
    {
        $conditions = collect($data['conditions'] ?? []);

        // A previously registered condition is only referenced by its diagnosis, it is not sent again
        $fhirConditions = $conditions
            ->map(
                function (array $condition, int $index) use ($data, $uuids): array {
                    if ($condition['isRegistered'] ?? false) {
                        return ['id' => $condition['uuid']];
                    }

                    if (isset($data['encounter']['diagnoses'][$index])) {
                        $condition['clinicalStatus'] = ConditionClinicalStatus::ACTIVE->value;
                    }

                    return Fhir::condition()->toFhir($condition, $uuids);
                }
            )
            ->values()
            ->toArray();

        $fhirImmunizations = collect($data['immunizations'] ?? [])
            ->map(fn (array $immunization) => Fhir::immunization()->toFhir($immunization, $uuids))
            ->values()
            ->toArray();

        $fhirDiagnosticReports = collect($data['diagnosticReports'] ?? [])
            ->map(
                function (array $diagnosticReport) use ($data, $uuids): array {
                    $encounterPeriodDate = data_get($data, 'encounter.periodDate');
                    $encounterPeriodStart = data_get($data, 'encounter.periodStart');
                    $encounterPeriodEnd = data_get($data, 'encounter.periodEnd');
                    $diagnosticReport['divisionId'] = data_get($data, 'encounter.divisionId');

                    if (($diagnosticReport['effectiveType'] ?? null) === 'period') {
                        $diagnosticReport['effectivePeriodStartDate'] = $encounterPeriodDate;
                        $diagnosticReport['effectivePeriodStartTime'] = $encounterPeriodStart;
                        $diagnosticReport['effectivePeriodEndDate'] = $encounterPeriodDate;
                        $diagnosticReport['effectivePeriodEndTime'] = $encounterPeriodEnd;
                    }

                    return Fhir::diagnosticReport()->toFhir(
                        $diagnosticReport,
                        array_merge($uuids, ['diagnosticReport' => $diagnosticReport['uuid'] ?? Str::uuid()->toString(), ]),
                        DiagnosticReportStatus::tryFrom($diagnosticReport['status'] ?? '')
                            ?? DiagnosticReportStatus::FINAL
                    );
                }
            )
            ->values()
            ->toArray();

        $fhirObservations = collect($data['observations'] ?? [])
            ->map(fn (array $observation) => Fhir::observation()->toFhir($observation, $uuids))
            ->values()
            ->toArray();

        $fhirProcedures = collect($data['procedures'] ?? [])
            ->map(fn (array $procedure): array => $this->toPackageDocument(app(ObjectMapperInterface::class)->map(new FormCollection($procedure), new ProcedureEhealth(
                id: $uuids['procedure'] ?? $procedure['uuid'] ?? Str::uuid()->toString(),
                legalEntity: legalEntity()->uuid,
                employee: $uuids['employee'],
                encounterUuid: $uuids['encounter'] ?? null,
            ))->toArray()))
            ->values()
            ->toArray();

        $fhirDevices = collect($data['devices'] ?? [])
            ->map(function (array $device) use ($uuids): array {
                return $this->toPackageDocument(app(ObjectMapperInterface::class)->map(new FormCollection($device), new DeviceEhealth(
                    id: $device['uuid'] ?? Str::uuid()->toString(),
                    encounter: $uuids['encounter'],
                    recorder: $uuids['employee'],
                ))->toArray());
            })
            ->values()
            ->toArray();

        $fhirDetectedIssues = collect($data['detectedIssues'] ?? [])
            ->map(function (array $detectedIssue) use ($uuids): array {
                $payload = app(ObjectMapperInterface::class)->map(new FormCollection($detectedIssue), new DetectedIssueEhealth(
                    id: $detectedIssue['uuid'] ?? Str::uuid()->toString(),
                    encounter: $uuids['encounter'],
                    recorder: $uuids['employee'],
                ))->toArray();

                return $this->toPackageDocument($payload);
            })
            ->values()
            ->toArray();

        $fhirDeviceAssociations = collect($this->dateDeviceAssociations($data['deviceAssociations'] ?? []))
            ->map(function (array $association) use ($uuids): array {
                return $this->toPackageDocument(app(ObjectMapperInterface::class)->map(new FormCollection($association), new DeviceAssociationEhealth(
                    id: $association['uuid'] ?? Str::uuid()->toString(),
                    encounter: $uuids['encounter'],
                    recorder: $uuids['employee'],
                ))->toArray());
            })
            ->values()
            ->toArray();

        $fhirClinicalImpressions = collect($data['clinicalImpressions'] ?? [])
            ->map(fn (array $clinicalImpression) => Fhir::clinicalImpression()->toFhir($clinicalImpression, $uuids))
            ->values()
            ->toArray();

        $fhirDeviceDispenses = collect($data['deviceDispenses'] ?? [])
            ->map(function (array $deviceDispense) use ($uuids): array {
                return $this->toPackageDocument(app(ObjectMapperInterface::class)->map(new FormCollection($deviceDispense), new DeviceDispenseEhealth(
                    id: $deviceDispense['uuid'] ?? Str::uuid()->toString(),
                    encounter: $uuids['encounter'],
                ))->toArray());
            })
            ->values()
            ->toArray();

        $referencedSpecimenIds = collect($data['observations'] ?? [])
            ->pluck('specimenId')
            ->merge(collect($data['diagnosticReports'] ?? [])->pluck('specimenIds')->flatten())
            ->filter()
            ->unique()
            ->all();

        $fhirSpecimens = collect($data['specimens'] ?? [])
            ->map(function (array $specimen) use ($referencedSpecimenIds, $uuids): array {
                $specimen['isReferenced'] = in_array($specimen['uuid'] ?? null, $referencedSpecimenIds, true);

                return $this->toPackageDocument(app(ObjectMapperInterface::class)->map(new FormCollection($specimen), new SpecimenEhealth(
                    id: $specimen['uuid'] ?? Str::uuid()->toString(),
                    legalEntity: legalEntity()->uuid,
                    employee: $uuids['employee'],
                    encounter: $uuids['encounter'],
                ))->toArray());
            })
            ->values()
            ->toArray();

        $encounterData = $data['encounter'];

        return [
            'encounter' => Fhir::encounter()->toFhir($encounterData, $fhirConditions, $uuids),
            'conditions' => collect($fhirConditions)
                ->reject(static fn (array $condition, int $index): bool => $conditions[$index]['isRegistered'] ?? false)
                ->values()
                ->toArray(),
            'immunizations' => $fhirImmunizations,
            'diagnosticReports' => $fhirDiagnosticReports,
            'observations' => $fhirObservations,
            'procedures' => $fhirProcedures,
            'detectedIssues' => $fhirDetectedIssues,
            'devices' => $fhirDevices,
            'deviceAssociations' => $fhirDeviceAssociations,
            'deviceDispenses' => $fhirDeviceDispenses,
            'specimens' => $fhirSpecimens,
            'clinicalImpressions' => $fhirClinicalImpressions
        ];
    }

    /** New opening/closing pairs must remain a minute apart after repository hydration. */
    private function dateDeviceAssociations(array $associations): array
    {
        $now = CarbonImmutable::now();
        $perDevice = array_count_values(array_column($associations, 'deviceId'));
        foreach ($associations as $index => $association) {
            if (!empty($association['recorded'])) {
                continue;
            }

            $opensPair = ($perDevice[$association['deviceId']] ?? 1) > 1
                && in_array($association['status'], [DeviceAssociationStatus::IMPLANTED->value, DeviceAssociationStatus::ATTACHED->value], true);
            $associations[$index]['recorded'] = ($opensPair ? $now->subMinute() : $now)->toIso8601ZuluString();
        }

        return $associations;
    }

    /** Temporary adapter for the remaining camelCase package boundary; retain nested JSON objects. */
    private function toPackageDocument(array $payload): array
    {
        $document = [];
        foreach ($payload as $field => $value) {
            $key = is_string($field) ? Str::camel($field) : $field;
            $document[$key] = is_array($value) ? $this->toPackageDocument($value) : $value;
        }

        return $document;
    }
}

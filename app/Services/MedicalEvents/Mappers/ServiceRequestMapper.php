<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents\Mappers;

use App\Contracts\FhirMapperContract;
use App\Core\Arr;
use App\Dto\ServiceRequest\Resource;
use App\Services\MedicalEvents\FhirResource;
use App\Services\MedicalEvents\InformWith;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Compatibility for legacy encounter and care-plan draft input; snapshot projections use entity DTOs. */
class ServiceRequestMapper implements FhirMapperContract
{
    private const CATEGORY_SYSTEM = 'eHealth/SNOMED/service_request_categories';

    /** Pure form projection over the original ESOZ resource; no generated identity or clock. */
    public function toFhir(array $data, mixed ...$context): array
    {
        $input = Arr::toCamelCase($data);
        $options = Arr::toCamelCase($context[0] ?? []);
        $resource = $options['resource'] ?? [];
        if (array_key_exists('uuid', $input)) {
            $this->set($resource, 'id', $input['uuid']);
        }
        if (array_key_exists('status', $input)) {
            $this->set($resource, 'status', $input['status']);
        }
        if (array_key_exists('intent', $input)) {
            $this->set($resource, 'intent', $input['intent']);
        }
        if (array_key_exists('priority', $input)) {
            $this->set($resource, 'priority', $input['priority']);
        }
        if (array_key_exists('requestNumber', $input)) {
            $this->set($resource, 'requisition', $input['requestNumber']);
        }
        if (array_key_exists('note', $input)) {
            $this->set($resource, 'note', $input['note']);
        }
        if (array_key_exists('patientInstruction', $input)) {
            $this->set($resource, 'patientInstruction', $input['patientInstruction']);
        }
        if (!empty($options['employeeUuid']) && data_get($resource, 'requesterEmployee.identifier.value') !== $options['employeeUuid']) {
            $resource['requesterEmployee'] = FhirResource::make()
                ->coding('eHealth/resources', 'employee')
                ->toIdentifier($options['employeeUuid']);
        }
        if (!empty($options['legalEntityUuid']) && data_get($resource, 'requesterLegalEntity.identifier.value') !== $options['legalEntityUuid']) {
            $resource['requesterLegalEntity'] = FhirResource::make()
                ->coding('eHealth/resources', 'legal_entity')
                ->toIdentifier($options['legalEntityUuid']);
        }
        if (!empty($options['personUuid']) && data_get($resource, 'subject.identifier.value') !== $options['personUuid']) {
            $resource['subject'] = FhirResource::make()
                ->coding('eHealth/resources', 'patient')
                ->toIdentifier($options['personUuid']);
        }
        if (empty($resource['id']) && !($options['allowMissingId'] ?? false)) {
            throw new InvalidArgumentException(__('referrals.errors.identity_required'));
        }
        $resource['status'] ??= 'draft';
        $resource['intent'] ??= 'order';
        foreach (['code' => ['serviceId', 'service', 'serviceType'], 'program' => ['programId', 'medical_program', null], 'performer' => ['performer', 'legal_entity', null], 'locationReference' => ['locationReference', 'division', null]] as $field => [$key, $defaultType, $typeKey]) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if (is_array($value)) {
                $resource[$field] = $value;
            } elseif ($value === null || $value === '') {
                // A read-only service label has no selectable identifier in the legacy form.
                if ($field === 'code' && isset($resource['code']['displayValue']) && !isset($resource['code']['identifier'])) {
                    continue;
                }
                unset($resource[$field]);
            } else {
                $existing = $resource[$field] ?? null;
                $type = $typeKey !== null ? ($input[$typeKey] ?? null) : null;
                $sameValue = data_get($existing, 'identifier.value') === (string) $value;
                $type ??= $sameValue ? data_get($existing, 'identifier.type.coding.0.code', $defaultType) : $defaultType;
                $resource[$field] = $this->reference((string) $value, $type, $sameValue ? $existing : null);
            }
        }
        foreach (['category' => self::CATEGORY_SYSTEM, 'performerType' => 'SPECIALITY_TYPE'] as $field => $system) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                if (is_array($value)) {
                    $resource[$field] = $value;
                } elseif ($value === null || $value === '') {
                    unset($resource[$field]);
                } elseif (data_get($resource, "$field.coding.0.code") !== $value) {
                    $resource[$field] = ['coding' => [['system' => $system, 'code' => $value]]];
                }
            }
        }
        if (array_key_exists('contextUuid', $input) || isset($options['encounterUuid']) || isset($options['episodeUuid'])) {
            $value = $input['contextUuid'] ?? ($options['encounterUuid'] ?? ($options['episodeUuid'] ?? null));
            $type = $input['contextType'] ?? (isset($options['encounterUuid']) ? 'encounter' : (isset($options['episodeUuid']) ? 'episode_of_care' : data_get($resource, 'context.identifier.type.coding.0.code')));
            $this->set($resource, 'context', $value ? $this->reference($value, $type, $resource['context'] ?? null) : null);
        }
        if (array_key_exists('basedOnUuid', $input)) {
            $items = $resource['basedOn'] ?? [];
            $previousActivity = collect($items)->first(fn (array $ref): bool => in_array(data_get($ref, 'identifier.type.coding.0.code'), ['activity', 'care_plan_activity'], true));
            $items = array_values(array_filter($items, static fn (array $ref): bool => !in_array(data_get($ref, 'identifier.type.coding.0.code'), ['activity', 'care_plan_activity'], true)));
            if (!empty($input['basedOnUuid'])) {
                $old = collect($resource['basedOn'] ?? [])->first(fn (array $ref): bool => data_get($ref, 'identifier.value') === $input['basedOnUuid']);
                $items[] = $this->reference($input['basedOnUuid'], 'activity', $old);
            }
            if (data_get($previousActivity, 'identifier.value') !== ($input['basedOnUuid'] ?: null)) {
                $resource['basedOn'] = $items;
            }
        }
        $previousCarePlan = collect($resource['basedOn'] ?? [])->first(fn (array $ref): bool => data_get($ref, 'identifier.type.coding.0.code') === 'care_plan');
        if (!empty($options['carePlanUuid']) && data_get($previousCarePlan, 'identifier.value') !== $options['carePlanUuid']) {
            $items = array_values(array_filter($resource['basedOn'] ?? [], static fn (array $ref): bool => data_get($ref, 'identifier.type.coding.0.code') !== 'care_plan'));
            array_unshift($items, $this->reference($options['carePlanUuid'], 'care_plan', collect($resource['basedOn'] ?? [])->first(fn (array $ref): bool => data_get($ref, 'identifier.type.coding.0.code') === 'care_plan')));
            $resource['basedOn'] = $items;
        }
        if (array_key_exists('quantity', $input)) {
            if ($input['quantity'] === null || $input['quantity'] === '') {
                unset($resource['quantity']);
            } elseif (is_array($input['quantity'])) {
                $resource['quantity'] = $input['quantity'];
            } else {
                if (!is_numeric($input['quantity']) || !is_finite((float) $input['quantity'])) {
                    throw new InvalidArgumentException(__('referrals.errors.quantity_invalid'));
                }
                if (!BigDecimal::of((string) $input['quantity'])->isEqualTo((string) (float) $input['quantity'])) {
                    throw new InvalidArgumentException(__('referrals.errors.quantity_precision'));
                }
                $resource['quantity'] = array_replace($resource['quantity'] ?? [], ['value' => (float) $input['quantity']]);
                $resource['quantity']['system'] ??= 'SERVICE_UNIT';
                $resource['quantity']['code'] ??= 'PIECE';
            }
        }
        foreach (['system', 'code', 'unit', 'comparator'] as $key) {
            if (array_key_exists('quantity'.ucfirst($key), $input) && isset($resource['quantity'])) {
                $this->set($resource['quantity'], $key, $input['quantity'.ucfirst($key)]);
            }
        }
        foreach (['startedAt' => 'start', 'endedAt' => 'end'] as $source => $target) {
            if (!array_key_exists($source, $input)) {
                continue;
            }
            $value = $input[$source];
            if ($value === null || $value === '') {
                if (isset($resource['occurrencePeriod'])) {
                    unset($resource['occurrencePeriod'][$target]);
                }
            } else {
                $existing = $resource['occurrencePeriod'][$target] ?? null;
                if ($existing !== $value) {
                    $resource['occurrencePeriod'][$target] = $this->dateTime((string) $value, $options['timezone'] ?? 'UTC');
                }
            }
        }
        foreach (['supportingInfo', 'reasonReference', 'permittedResources', 'specimens', 'focus', 'basedOn'] as $field) {
            if (array_key_exists($field, $input)) {
                $items = $input[$field];
                $items = is_string($items) ? json_decode($items, true, flags: JSON_THROW_ON_ERROR) : ($items ?? []);
                $resource[$field] = array_map(function (array $item) use ($resource, $field): array {
                    if (isset($item['identifier'])) {
                        return $item;
                    }
                    if (empty($item['uuid']) && is_string($item['displayValue'] ?? null) && trim($item['displayValue']) !== '') {
                        return ['displayValue' => $item['displayValue']];
                    }
                    $old = collect($resource[$field] ?? [])->first(fn (array $ref): bool => data_get($ref, 'identifier.value') === ($item['uuid'] ?? null));

                    $reference = $this->reference($item['uuid'] ?? '', isset($item['type']) ? (strtoupper($item['type']) === $item['type'] ? strtolower($item['type']) : Str::snake($item['type'])) : null, $old);
                    if (array_key_exists('displayValue', $item)) {
                        $reference['displayValue'] = $item['displayValue'];
                    }

                    return $reference;
                }, $items);
            }
        }
        if (array_key_exists('informWith', $input)) {
            $id = is_array($input['informWith']) ? ($input['informWith']['authMethodId'] ?? InformWith::authMethodId($input['informWith'])) : InformWith::authMethodId($input['informWith']);
            $this->set($resource, 'informWith', $id === null ? null : ['authMethodId' => $id]);
        }
        foreach (['occurrenceDateTime', 'occurrencePeriod'] as $field) {
            if (array_key_exists($field, $input)) {
                $this->set($resource, $field, $input[$field]);
            }
        }
        if (!empty($resource['occurrencePeriod']) && !empty($resource['occurrenceDateTime'])) {
            throw new InvalidArgumentException(__('referrals.errors.occurrence_conflict'));
        }

        return $resource;
    }

    public function normalize(array $resource): array
    {
        return (new Resource($resource))->normalized();
    }

    public function fromFhir(array $data, mixed ...$context): array
    {
        return (new Resource($data))->formFields();
    }

    public function createContent(array $resource): array
    {
        return (new Resource($resource))->createContent();
    }

    public function toPrequalifyPayload(array $data, array $uuids, ?string $carePlanUuid = null, ?string $activityUuid = null): array
    {
        if ($activityUuid !== null) {
            $data['based_on_uuid'] = $activityUuid;
        }
        $resource = $this->toFhir($data, $uuids + ['allowMissingId' => true, 'carePlanUuid' => $carePlanUuid]);
        $content = $this->createContent($resource);
        unset($content['id'], $content['program']);

        return ['service_request' => $content, 'programs' => isset($resource['program']) ? [Arr::toSnakeCase($resource['program'])] : []];
    }

    /** Compatibility for callers that still inspect the prequalify-shaped wrapper. */
    public function toCreateSignedPayload(array $data, array $uuids, ?string $carePlanUuid = null, ?string $activityUuid = null): array
    {
        $payload = $this->toPrequalifyPayload($data, $uuids, $carePlanUuid, $activityUuid);
        if (empty($data['uuid'])) {
            throw new InvalidArgumentException(__('referrals.errors.identity_required'));
        }
        $payload['service_request']['id'] = $data['uuid'];

        return $payload;
    }

    public function toCreateSignedContent(array $data, array $uuids, ?string $carePlanUuid = null, ?string $activityUuid = null): array
    {
        if ($activityUuid !== null) {
            $data['based_on_uuid'] = $activityUuid;
        }

        return $this->createContent($this->toFhir($data, $uuids + ['carePlanUuid' => $carePlanUuid]));
    }

    private function reference(string $value, ?string $type, ?array $original = null): array
    {
        if ($value === '' || $type === null || $type === '') {
            throw new InvalidArgumentException(__('referrals.errors.reference_incomplete'));
        }
        if (data_get($original, 'identifier.value') === $value && data_get($original, 'identifier.type.coding.0.code') === $type) {
            return $original;
        }

        return FhirResource::make()->coding('eHealth/resources', $type)->toIdentifier($value);
    }

    private function dateTime(string $value, string $timezone): string
    {
        return CarbonImmutable::parse($value, $timezone)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    private function set(array &$resource, string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            unset($resource[$field]);
        } else {
            $resource[$field] = $value;
        }
    }
}

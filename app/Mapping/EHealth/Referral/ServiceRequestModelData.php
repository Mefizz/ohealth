<?php

declare(strict_types=1);

namespace App\Mapping\EHealth\Referral;

use App\Mapping\Transforms\FallbackValue;
use App\Dto\ServiceRequest\UseResponse;
use App\Dto\ServiceRequest\ExternalResponse;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use ArrayObject;
use stdClass;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\SourceClass;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

final class ServiceRequestModelData extends ReferralModelData
{
    #[Map(source: '[service_id?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'code?[identifier?][value?]', if: new SourceClass(stdClass::class), transform: new FallbackValue('code.coding.0.code', 'service.id'))]
    #[Map(source: '[service_id?]', if: new SourceClass(ServiceRequestRequest::class))]
    #[Map(source: 'data[code?][identifier?][value?]', if: new SourceClass(UseResponse::class), transform: [new FallbackValue('data.code.coding.0.code'), [self::class, 'mapUseProduct']])]
    #[Map(source: 'data[code?][identifier?][value?]', if: new SourceClass(ExternalResponse::class), transform: new FallbackValue('data.code.coding.0.code', 'data.service.id'))]
    public ?string $service_id = null;

    #[Map(source: '[patient_instruction?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'patientInstruction?', if: new SourceClass(stdClass::class), transform: new FallbackValue('patient_instruction'))]
    #[Map(source: '[patient_instruction?]', if: new SourceClass(ServiceRequestRequest::class))]
    #[Map(source: 'data[patientInstruction?]', if: new SourceClass(ExternalResponse::class), transform: new FallbackValue('data.patient_instruction'))]
    public ?string $patient_instruction = null;

    #[Map(source: '[inform_with?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'informWith?', if: new SourceClass(stdClass::class), transform: new FallbackValue('inform_with'))]
    #[Map(source: '[inform_with?]', if: new SourceClass(ServiceRequestRequest::class))]
    #[Map(source: 'data[informWith?]', if: new SourceClass(ExternalResponse::class), transform: new FallbackValue('data.inform_with'))]
    public mixed $inform_with = null;

    #[Map(source: '[reason_reference?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'reasonReference?', if: new SourceClass(stdClass::class), transform: [new FallbackValue('reason_reference'), [self::class, 'referenceSources'], new MapCollection(targetClass: ReferralReferenceData::class)])]
    #[Map(source: '[reason_reference?]', if: new SourceClass(ServiceRequestRequest::class))]
    #[Map(source: 'data[reasonReference?]', if: new SourceClass(ExternalResponse::class), transform: [new FallbackValue('data.reason_reference'), [self::class, 'externalReasonSources'], new MapCollection(targetClass: ReferralReferenceData::class)])]
    public ?array $reason_reference = null;

    #[Map(source: '[supporting_info?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'supporting_info?', if: new SourceClass(stdClass::class), transform: [new FallbackValue('supportingInfo'), [self::class, 'completeReferenceSources'], new MapCollection(targetClass: ReferralReferenceData::class)])]
    #[Map(source: '[supporting_info?]', if: new SourceClass(ServiceRequestRequest::class))]
    #[Map(source: 'data[supportingInfo?]', if: new SourceClass(ExternalResponse::class), transform: [[self::class, 'externalSupportingSources'], new MapCollection(targetClass: ReferralReferenceData::class)])]
    public ?array $supporting_info = null;

    #[Map(source: 'data[basedOn?][0?][identifier?][value?]', if: new SourceClass(ExternalResponse::class))]
    public ?string $based_on_uuid = null;

    #[Map(source: 'data[context?][identifier?][value?]', if: new SourceClass(ExternalResponse::class))]
    public ?string $context_uuid = null;

    /** Full search import preserves nulls and incomplete references; it must not use the sync patch policy. */
    public function toExternalRecord(): array
    {
        $data = [...$this->toArray(), 'based_on_uuid' => $this->based_on_uuid, 'context_uuid' => $this->context_uuid];
        $fields = ['uuid', 'status', 'request_number', 'started_at', 'ended_at', 'service_id', 'quantity',
            'program_id', 'intent', 'category', 'based_on_uuid', 'context_uuid', 'priority', 'note',
            'patient_instruction', 'reason_reference', 'inform_with', 'supporting_info'];

        return array_replace(array_fill_keys($fields, null), array_intersect_key($data, array_flip($fields)));
    }

    /** Identifier relationships are imported only through the complete search contract. */
    public function toArray(): array
    {
        $data = parent::toArray();
        unset($data['based_on_uuid'], $data['context_uuid']);

        return $data;
    }

    /** Preserve incomplete rows while excluding local uuid/type aliases from this API contract. */
    public static function externalSupportingSources(mixed $value): array
    {
        return array_map(static fn (mixed $row): stdClass => (object) ['identifier' => data_get($row, 'identifier')], array_values(is_array($value) ? $value : []));
    }

    public static function externalReasonSources(mixed $value): array
    {
        return self::externalSupportingSources(array_filter(is_array($value) ? $value : [], is_array(...)));
    }

    /** The use response creates only the minimum executor record; GET sync keeps its own policy. */
    public function toUseRecord(): array
    {
        return array_intersect_key($this->toArray(), array_flip(['request_number', 'program_id', 'service_id', 'quantity', 'category', 'intent']));
    }

    public static function mapUseProduct(mixed $value): string
    {
        return $value ?? '';
    }

}

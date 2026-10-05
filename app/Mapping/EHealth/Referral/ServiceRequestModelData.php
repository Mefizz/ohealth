<?php

declare(strict_types=1);

namespace App\Mapping\EHealth\Referral;

use App\Mapping\Transforms\FallbackValue;
use App\Dto\ServiceRequest\UseResponse;
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
    public ?string $service_id = null;

    #[Map(source: '[patient_instruction?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'patientInstruction?', if: new SourceClass(stdClass::class), transform: new FallbackValue('patient_instruction'))]
    #[Map(source: '[patient_instruction?]', if: new SourceClass(ServiceRequestRequest::class))]
    public ?string $patient_instruction = null;

    #[Map(source: '[inform_with?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'informWith?', if: new SourceClass(stdClass::class), transform: new FallbackValue('inform_with'))]
    #[Map(source: '[inform_with?]', if: new SourceClass(ServiceRequestRequest::class))]
    public mixed $inform_with = null;

    #[Map(source: '[reason_reference?]', if: new SourceClass(ArrayObject::class))]
    #[Map(source: 'reasonReference?', if: new SourceClass(stdClass::class), transform: [new FallbackValue('reason_reference'), [self::class, 'referenceSources'], new MapCollection(targetClass: ReferralReferenceData::class)])]
    #[Map(source: '[reason_reference?]', if: new SourceClass(ServiceRequestRequest::class))]
    public ?array $reason_reference = null;

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

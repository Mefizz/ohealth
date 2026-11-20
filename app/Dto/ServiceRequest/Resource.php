<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest;

use App\Core\Arr;
use App\Dto\FormCollection;
use App\Dto\ServiceRequest\Resource\Form;
use App\Dto\ServiceRequest\Resource\Model;
use App\Services\MedicalEvents\FhirResource;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;

/** A referral snapshot from local storage or a validated response from ЕСОЗ. */
class Resource
{
    protected ObjectMapper $objectMapper;

    public function __construct(protected array $values)
    {
        $this->objectMapper = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor());
    }

    public function normalized(): array
    {
        $resource = Arr::toCamelCase($this->values);
        if (isset($resource['category']) && array_is_list($resource['category']) && count($resource['category']) === 1) {
            $resource['category'] = $resource['category'][0];
        }
        if (isset($resource['quantityInteger']) && !isset($resource['quantity'])) {
            $resource['quantity'] = ['value' => $resource['quantityInteger'], 'system' => 'SERVICE_UNIT', 'code' => 'PIECE'];
            unset($resource['quantityInteger']);
        }
        if (isset($resource['code']['coding'][0]['code']) && !isset($resource['code']['identifier'])) {
            $resource['code'] = FhirResource::make()->coding('eHealth/resources', 'service')->toIdentifier((string) $resource['code']['coding'][0]['code']);
        }
        if (isset($resource['note']) && is_array($resource['note'])) {
            $resource['note'] = implode("\n", array_column($resource['note'], 'text'));
        }
        // Local snapshots can carry a numeric database key alongside the clinical UUID.
        $id = $resource['id'] ?? null;
        if (isset($resource['uuid']) && ($id === null || is_int($id) || (is_string($id) && ctype_digit($id)))) {
            $resource['id'] = $resource['uuid'];
            unset($resource['uuid']);
        }

        if (isset($resource['requestNumber']) && !isset($resource['requisition'])) {
            $resource['requisition'] = $resource['requestNumber'];
            unset($resource['requestNumber']);
        }

        return $resource;
    }

    public function modelData(): Model
    {
        $snapshot = $this->normalized();
        if (!Str::isUuid($snapshot['id'] ?? null)) {
            throw new InvalidArgumentException(__('referrals.errors.identity_and_service_required'));
        }
        $data = $this->objectMapper->map(new FormCollection($snapshot), Model::class);
        if (!$data->hasServiceReference()) {
            throw new InvalidArgumentException(__('referrals.errors.identity_and_service_required'));
        }
        $data->snapshot = $snapshot;

        return $data;
    }

    public function formFields(): array
    {
        $form = $this->objectMapper->map(new FormCollection($this->normalized()), Form::class);

        return $form->toArray();
    }

    public function createContent(): array
    {
        $content = $this->objectMapper->map(new FormCollection($this->normalized()), Ehealth::class);

        return $content->toArray();
    }
}

<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Core\Arr;
use App\Dto\Input\FormCollection;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** The clinical content signed for Create Care Plan, projected from the validated draft resource. */
#[Map(source: FormCollection::class)]
class Ehealth
{
    #[Map(source: '[id]')]
    public string $id;

    #[Map(source: '[category]')]
    public array $category;

    #[Map(source: '[title]')]
    public string $title;

    #[Map(source: '[description?]')]
    public ?string $description = null;

    #[Map(source: '[period]')]
    public array $period;

    #[Map(source: '[supportingInfo?]')]
    public ?array $supportingInfo = null;

    #[Map(source: '[note?]')]
    public ?string $note = null;

    #[Map(source: '[encounter]')]
    public array $encounter;

    #[Map(source: '[addresses]')]
    public array $addresses;

    #[Map(source: '[author]')]
    public array $author;

    #[Map(source: '[contributor?]')]
    public ?array $contributor = null;

    #[Map(source: '[termsOfService]')]
    public array $termsOfService;

    #[Map(source: '[informWith?]')]
    public ?array $informWith = null;

    #[Map(if: false)]
    public string $intent = 'order';

    #[Map(if: false)]
    public string $status = 'new';

    public function toArray(): array
    {
        return $this->withoutEmptyValues(Arr::toSnakeCase(get_object_vars($this)));
    }

    /** Preserve zero-valued codes and keep filtered lists as JSON arrays when signing. */
    protected function withoutEmptyValues(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->withoutEmptyValues($value);
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $result[$key] = $value;
            }
        }

        return array_is_list($data) ? array_values($result) : $result;
    }
}

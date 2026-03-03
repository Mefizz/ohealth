<?php

declare(strict_types=1);

namespace App\Models\TreatmentPlan;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class TreatmentPlan
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $ehealthId
 * @property string|null $patientId
 * @property \App\Enums\TreatmentPlan\Category $category
 * @property \App\Enums\TreatmentPlan\Intention $intention
 * @property \App\Enums\TreatmentPlan\TermsService $termsService
 * @property string $nameTreatmentPlan
 * @property \Illuminate\Support\Carbon|null $periodStart
 * @property \Illuminate\Support\Carbon|null $periodEnd
 * @property string $status
 * @property array|null $addresses
 * @property string|null $encounter
 * @property string|null $author
 * @property string|null $description
 * @property array|null $supportingInfo
 * @property string|null $note
 * @property string|null $informWith
 * @property string|null $jobId
 * @property array|null $validationDetails
 * @property \Illuminate\Support\Carbon|null $insertedAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 */
class TreatmentPlan extends Model
{
    use HasFactory;
    use HasCamelCasing;

    protected $fillable = [
        'uuid',
        'ehealthId',
        'patientId',
        'category',
        'intention',
        'termsService',
        'nameTreatmentPlan',
        'periodStart',
        'periodEnd',
        'status',
        'addresses',
        'encounter',
        'author',
        'description',
        'supportingInfo',
        'note',
        'informWith',
        'jobId',
        'validationDetails',
    ];

    protected $casts = [
        'category' => \App\Enums\TreatmentPlan\Category::class,
        'intention' => \App\Enums\TreatmentPlan\Intention::class,
        'termsService' => \App\Enums\TreatmentPlan\TermsService::class,
        'periodStart' => 'datetime',
        'periodEnd' => 'datetime',
        'addresses' => 'array',
        'supportingInfo' => 'array',
        'validationDetails' => 'array',
    ];

    public function activities(): HasMany
    {
        return $this->hasMany(TreatmentPlanActivity::class, 'treatment_plan_id');
    }
}

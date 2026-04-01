<?php

declare(strict_types=1);

namespace App\Models\MedicalEvents\Sql;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReferenceRange extends Model
{
    use HasCamelCasing;

    protected $fillable = [
        'referenceable_type',
        'referenceable_id',
        'low_id',
        'high_id',
        'type_id',
        'applies_to_id',
        'age_low_id',
        'age_high_id',
        'text'
    ];

    protected $hidden = [
        'id',
        'referenceable_type',
        'referenceable_id',
        'low_id',
        'high_id',
        'type_id',
        'applies_to_id',
        'age_low_id',
        'age_high_id',
        'created_at',
        'updated_at'
    ];

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function low(): BelongsTo
    {
        return $this->belongsTo(Quantity::class, 'low_id');
    }

    public function high(): BelongsTo
    {
        return $this->belongsTo(Quantity::class, 'high_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CodeableConcept::class, 'type_id');
    }

    public function appliesTo(): BelongsTo
    {
        return $this->belongsTo(CodeableConcept::class, 'applies_to_id');
    }

    public function ageLow(): BelongsTo
    {
        return $this->belongsTo(Quantity::class, 'age_low_id');
    }

    public function ageHigh(): BelongsTo
    {
        return $this->belongsTo(Quantity::class, 'age_high_id');
    }
}

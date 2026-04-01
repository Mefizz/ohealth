<?php

declare(strict_types=1);

namespace App\Models\MedicalEvents\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Quantity extends Model
{
    protected $fillable = [
        'value',
        'comparator',
        'unit',
        'system',
        'code'
    ];

    protected $hidden = [
        'id',
        'quantifiable_type',
        'quantifiable_id',
        'created_at',
        'updated_at'
    ];

    public function quantifiable(): MorphTo
    {
        return $this->morphTo();
    }
}

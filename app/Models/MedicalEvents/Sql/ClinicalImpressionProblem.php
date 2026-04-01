<?php

declare(strict_types=1);

namespace App\Models\MedicalEvents\Sql;

use Illuminate\Database\Eloquent\Model;

class ClinicalImpressionProblem extends Model
{
    protected $fillable = [
        'clinical_impression_id',
        'identifier_id'
    ];
}

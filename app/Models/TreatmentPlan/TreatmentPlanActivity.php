<?php

namespace App\Models\TreatmentPlan;

use Illuminate\Database\Eloquent\Model;

class TreatmentPlanActivity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'treatment_plan_id',
        'ehealth_id',
        'status',
        'detail_kind',
        'medical_program_id',
        'funding_source',
        'category',
        'reason_condition_id',
        'code',
        'code_detail',
        'quantity_value',
        'quantity_system',
        'quantity_code',
        'instruction',
        'timing',
        'job_id',
        'validation_details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'code_detail' => 'array',
        'timing' => 'array',
        'validation_details' => 'array',
    ];

    /**
     * Get the treatment plan that owns the activity.
     */
    public function treatmentPlan()
    {
        return $this->belongsTo(TreatmentPlan::class);
    }
}

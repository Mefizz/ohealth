<?php

declare(strict_types=1);

namespace App\Models\MedicalEvents\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Coding extends Model
{
    protected $fillable = [
        'system',
        'code'
    ];

    protected $hidden = [
        'id',
        'codeable_type',
        'codeable_id',
        'created_at',
        'updated_at'
    ];

    protected $appends = [
        'display'
    ];

    public function codeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getDisplayAttribute(): ?string
    {
        try {
            $dict = dictionary()->basics()->byName($this->system);
            if ($dict) {
                return $dict->asCodeDescription()->get($this->code) ?? $this->code;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $this->code;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurveClinic extends Model
{
    protected $table = 'curve_clinics';

    protected $guarded = [];

    protected $casts = [
        'is_active'        => 'boolean',
        'curve_updated_at' => 'datetime',
        'raw'              => 'array',
    ];

    public function operatories(): HasMany
    {
        return $this->hasMany(CurveOperatory::class, 'curve_clinic_id', 'curve_id');
    }
}

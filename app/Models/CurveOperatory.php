<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurveOperatory extends Model
{
    protected $table = 'curve_operatories';

    protected $guarded = [];

    protected $casts = [
        'is_bookable'      => 'boolean',
        'is_active'        => 'boolean',
        'curve_updated_at' => 'datetime',
        'raw'              => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(CurveClinic::class, 'curve_clinic_id', 'curve_id');
    }
}

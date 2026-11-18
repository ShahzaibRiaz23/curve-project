<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurveAppointmentTag extends Model
{
    protected $table = 'curve_appointment_tags';

    protected $guarded = [];

    protected $casts = [
        'is_active'        => 'boolean',
        'curve_updated_at' => 'datetime',
        'raw'              => 'array',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurveAppointment extends Model
{
    protected $table = 'curve_appointments';

    protected $guarded = [];

    /** Fields removed from the record before it is saved in `raw`. */
    public const RAW_EXCLUDE = ['description'];

    protected $casts = [
        'start_time'       => 'datetime',
        'curve_created_at' => 'datetime',
        'curve_updated_at' => 'datetime',
        'ghl_synced_at'    => 'datetime',
        'is_recurring'     => 'boolean',
        'is_booked_online' => 'boolean',
        'tag_ids'          => 'array',
        'raw'              => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(CurvePatient::class, 'curve_patient_id', 'curve_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(CurveClinic::class, 'curve_clinic_id', 'curve_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CurveProvider::class, 'curve_provider_id', 'curve_id');
    }
}

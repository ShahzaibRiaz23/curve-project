<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurvePatient extends Model
{
    protected $table = 'curve_patients';

    protected $guarded = [];

    /** Fields removed from the record before it is saved in `raw`. */
    public const RAW_EXCLUDE = [
        'dob', 'dateOfBirth', 'address', 'billingAddress',
        'emergencyContact', 'emergencyPhone', 'familyDoctor', 'familyDoctorPhone',
        'profileImage', 'imageId', 'nameOfSchool',
    ];

    protected $casts = [
        'curve_created_at' => 'datetime',
        'raw'              => 'encrypted:array', // patient data is stored encrypted
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(CurveAppointment::class, 'curve_patient_id', 'curve_id');
    }
}

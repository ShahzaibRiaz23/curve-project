<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurveProvider extends Model
{
    protected $table = 'curve_providers';

    protected $guarded = [];

    protected $casts = [
        'clinic_ids' => 'array',
        'raw'        => 'array',
    ];
}

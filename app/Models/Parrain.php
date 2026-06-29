<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parrain extends Model
{
    protected $table = 'parrains';

    protected $fillable = [
        'code',
        'station_de_lavage_id',
        'station_service_id',
        'commercial_id',
    ];
}

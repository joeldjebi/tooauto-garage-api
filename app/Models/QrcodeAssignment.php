<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrcodeAssignment extends Model
{
    protected $table = 'qrcode_assignments';

    protected $fillable = [
        'station_id',
        'station_service_id',
        'lavage_id',
        'station_de_lavage_id',
        'commercial_id',
        'user_id',
        'qrcode_id',
        'professionnel_id',
        'etablissement_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function qrcodeGenerate(): BelongsTo
    {
        return $this->belongsTo(QrcodeGenerate::class, 'qrcode_id');
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'user_id', 'user_id');
    }

    public function professionnel(): BelongsTo
    {
        return $this->belongsTo(Professionnel::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
	
    public function marque(): BelongsTo
    {
        return $this->belongsTo(Marque::class);
    }
}

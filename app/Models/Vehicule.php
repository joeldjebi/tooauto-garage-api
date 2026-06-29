<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicule extends Model
{
    protected $table = 'vehicules';

    protected $fillable = [
        'matricule',
        'carte_grise',
        'photos',
        'user_id',
        'type_de_vehicule_id',
        'marque_id',
        'modele',
        'type_de_carburant_id',
        'couleur',
        'statut',
        'gestionnaire_de_flotte_id',
        'couleur_vehicule_id',
        'qrcode_generate_id',
        'created_by',
        'provenance',
        'provenance_by',
        'chauffeur_id',
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    public function qrcodeGenerate(): BelongsTo
    {
        return $this->belongsTo(QrcodeGenerate::class, 'qrcode_generate_id');
    }

    public function marque(): BelongsTo
    {
        return $this->belongsTo(Marque::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}

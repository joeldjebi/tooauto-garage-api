<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $table = 'promotions';

    protected $fillable = [
        'libelle',
        'mobile',
        'date_debut',
        'date_fin',
        'image',
        'statut',
        'description',
        'etablissement_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => 'integer',
            'etablissement_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Professionnel::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $table = 'articles';

    protected $fillable = [
        'libelle',
        'description',
        'image',
        'amount',
        'etablissement_id',
        'created_by',
        'gestionnaire_de_flotte_id',
    ];

    protected function casts(): array
    {
        return [
            'etablissement_id' => 'integer',
            'created_by' => 'integer',
            'gestionnaire_de_flotte_id' => 'integer',
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

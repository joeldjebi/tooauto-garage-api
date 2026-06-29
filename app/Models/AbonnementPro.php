<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbonnementPro extends Model
{
    protected $table = 'abonnement_pros';

    protected $fillable = [
        'etablissement_id',
        'forfait_pro_id',
        'date_debut',
        'date_fin',
    ];

    protected function casts(): array
    {
        return [
            'etablissement_id' => 'integer',
            'forfait_pro_id' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function forfaitPro(): BelongsTo
    {
        return $this->belongsTo(ForfaitPro::class, 'forfait_pro_id');
    }
}

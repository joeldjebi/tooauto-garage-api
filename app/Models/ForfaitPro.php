<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForfaitPro extends Model
{
    protected $table = 'forfait_pros';

    protected $fillable = [
        'nom',
        'duree',
        'prix',
        'avantages',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'duree' => 'integer',
            'prix' => 'integer',
            'statut' => 'integer',
        ];
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(AbonnementPro::class, 'forfait_pro_id');
    }
}

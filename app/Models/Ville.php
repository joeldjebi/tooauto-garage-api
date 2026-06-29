<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ville extends Model
{
    protected $table = 'villes';

    protected $fillable = ['libelle', 'pays_id'];

    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLocalisation extends Model
{
    protected $table = 'share_localisations';

    protected $fillable = [
        'usager_id',
        'professionnel_id',
        'etablissement_id',
        'longitude',
        'latitude',
    ];

    public function usager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usager_id');
    }
}

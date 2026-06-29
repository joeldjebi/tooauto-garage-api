<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QrcodeGenerate extends Model
{
    protected $table = 'qrcode_generates';

    protected $fillable = [
        'qrcode',
        'is_assigned',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_assigned' => 'boolean',
            'assigned_at' => 'datetime',
        ];
    }

    public function vehicule(): HasOne
    {
        return $this->hasOne(Vehicule::class, 'qrcode_generate_id');
    }
}

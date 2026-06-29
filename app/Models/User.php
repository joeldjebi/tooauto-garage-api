<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'nom',
        'prenoms',
        'indicatif',
        'mobile',
        'email',
        'is_whatsapp',
        'commercial_id',
        'password',
        'statut',
        'avatar',
        'station_id',
        'station_service_id',
        'qrcode_generate_id',
        'station_de_lavage_id',
        'lavage_id',
        'professionnel_id',
        'etablissement_id',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function usager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usager_id');
    }
}
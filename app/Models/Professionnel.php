<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Professionnel extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'professionnels';

    protected $fillable = [
        'nom',
        'prenoms',
        'role',
        'email',
        'mobile',
        'password',
        'created_by',
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

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function etablissement(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Etablissement::class);
    }
}

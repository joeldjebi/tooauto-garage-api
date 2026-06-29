<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etablissement extends Model
{
    protected $table = 'etablissements';

    protected $fillable = [
        'name',
        'indicatif',
        'mobile',
        'mobile_fix',
        'email',
        'description',
        'logo',
        'cover',
        'logo_where_is_create',
        'cover_where_is_create',
        'adresse',
        'adresse_map',
        'longitude',
        'latitude',
        'professionnel_id',
        'pays_id',
        'ville_id',
        'commune_id',
        'specialite',
        'type_etablissement_id',
        'categorie_service_id',
        'is_whatsapp',
        'type_de_prestations',
        'service_mobile',
        'cover_create_by',
        'logo_create_by',
        'statut',
        'code_parrain',
        'is_commercial',
    ];

    protected function casts(): array
    {
        return [
            'logo_where_is_create' => 'integer',
            'cover_where_is_create' => 'integer',
            'type_etablissement_id' => 'integer',
            'categorie_service_id' => 'integer',
            'is_whatsapp' => 'integer',
            'service_mobile' => 'integer',
            'cover_create_by' => 'integer',
            'logo_create_by' => 'integer',
            'statut' => 'integer',
            'is_commercial' => 'integer',
            'type_de_prestations' => 'array',
        ];
    }

    public function professionnel(): BelongsTo
    {
        return $this->belongsTo(Professionnel::class);
    }

    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function typeEtablissement(): BelongsTo
    {
        return $this->belongsTo(TypeEtablissement::class);
    }

    public function categorieService(): BelongsTo
    {
        return $this->belongsTo(CategorieService::class);
    }

    public function annonces(): BelongsToMany
    {
        return $this->belongsToMany(Annonce::class, 'annonce_etablissements')
            ->withPivot('is_visible')
            ->withTimestamps();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function abonnementPros(): HasMany
    {
        return $this->hasMany(AbonnementPro::class, 'etablissement_id');
    }
}

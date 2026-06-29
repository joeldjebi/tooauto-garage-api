<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Annonce extends Model
{
    protected $table = 'annonces';

    protected $fillable = [
        'libelle',
        'image',
        'description',
        'gestionnaire_de_flotte_id',
        'usager_id',
        'etablissement_id',
        'type_etablissement_id',
        'type_de_piece_id',
        'marque_id',
        'modele',
        'statut',
        'mobile',
        'is_whatsapp',
        'categorie_piece_id',
        'sous_categorie_piece_id',
    ];

    protected function casts(): array
    {
        return [
            'type_etablissement_id' => 'integer',
            'type_de_piece_id' => 'integer',
            'marque_id' => 'integer',
            'categorie_piece_id' => 'integer',
            'sous_categorie_piece_id' => 'integer',
            'statut' => 'integer',
            'is_whatsapp' => 'integer',
        ];
    }

    public function usager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usager_id');
    }

    public function etablissements(): BelongsToMany
    {
        return $this->belongsToMany(Etablissement::class, 'annonce_etablissements')
            ->withPivot('is_visible')
            ->withTimestamps();
    }

    public function annonceEtablissements()
    {
        return $this->hasMany(AnnonceEtablissement::class);
    }
	
	public function sous_categorie_piece(): BelongsTo
    {
        return $this->belongsTo(Sous_categorie_piece::class);
    }
		
	public function categorie_piece(): BelongsTo
    {
        return $this->belongsTo(Categorie_piece::class);
    }
			
	public function marque(): BelongsTo
    {
        return $this->belongsTo(Marque::class);
    }
				
	public function type_de_piece(): BelongsTo
    {
        return $this->belongsTo(Type_de_piece::class);
    }
}

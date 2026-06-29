<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorieService extends Model
{
    protected $table = 'categorie_services';

    protected $fillable = ['libelle', 'image', 'description', 'statut', 'pro_or_usager', 'is_pro'];
}

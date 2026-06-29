<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeEtablissement extends Model
{
    protected $table = 'type_etablissements';

    protected $fillable = ['libelle'];
}

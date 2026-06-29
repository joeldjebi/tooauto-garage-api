<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDePrestation extends Model
{
    protected $table = 'type_de_prestations';

    protected $fillable = ['libelle', 'image'];
}

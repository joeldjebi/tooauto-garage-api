<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDeCarburant extends Model
{
    protected $table = 'type_de_carburants';

    protected $fillable = ['libelle'];
}

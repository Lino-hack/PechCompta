<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParametrePrix extends Model
{
    use HasFactory;

    protected $table = 'parametre_prixes';

    protected $fillable = ['nom', 'valeur_defaut'];
}

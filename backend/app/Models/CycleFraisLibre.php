<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleFraisLibre extends Model
{
    use HasFactory;

    protected $fillable = ['cycle_camion_id', 'libelle', 'montant'];
}

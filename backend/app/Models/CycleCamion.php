<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCamion extends Model
{
    use HasFactory;

    protected $fillable = ['date_debut', 'date_fin', 'frais_route', 'statut'];

    public function fraisLibres()
    {
        return $this->hasMany(CycleFraisLibre::class);
    }
}

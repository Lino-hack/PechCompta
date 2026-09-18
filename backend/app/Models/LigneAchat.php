<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LigneAchat extends Model
{
    use HasFactory;

    protected $fillable = ['source_achat_id', 'type_poisson_id', 'poids_kg', 'prix', 'heure'];

    public function sourceAchat()
    {
        return $this->belongsTo(SourceAchat::class);
    }

    public function typePoisson()
    {
        return $this->belongsTo(TypePoisson::class);
    }
}

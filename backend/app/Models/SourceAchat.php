<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceAchat extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'pecheur_id', 'detaillant_id', 'date'];

    public function pecheur()
    {
        return $this->belongsTo(Pecheur::class);
    }

    public function detaillant()
    {
        return $this->belongsTo(Detaillant::class);
    }

    public function lignesAchats()
    {
        return $this->hasMany(LigneAchat::class);
    }
}

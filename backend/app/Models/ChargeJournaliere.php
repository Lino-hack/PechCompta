<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargeJournaliere extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'nb_bagues_glace', 'prix_bague_utilise', 'transport'];

    public function chargesLibres()
    {
        return $this->hasMany(ChargeLibre::class);
    }
}

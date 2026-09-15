<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargeLibre extends Model
{
    use HasFactory;

    protected $fillable = ['charge_journaliere_id', 'libelle', 'montant'];

    public function chargeJournaliere()
    {
        return $this->belongsTo(ChargeJournaliere::class);
    }
}

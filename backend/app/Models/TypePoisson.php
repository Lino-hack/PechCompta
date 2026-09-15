<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypePoisson extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function lignesAchats()
    {
        return $this->hasMany(LigneAchat::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pecheur extends Model
{
    use HasFactory;

    protected $fillable = ['nom'];

    public function sourcesAchats()
    {
        return $this->hasMany(SourceAchat::class);
    }
}

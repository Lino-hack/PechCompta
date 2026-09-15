<?php

namespace Database\Seeders;

use App\Models\ParametrePrix;
use Illuminate\Database\Seeder;

class ParametrePrixSeeder extends Seeder
{
    public function run(): void
    {
        ParametrePrix::firstOrCreate(['nom' => 'prix_bac_glace'], ['valeur_defaut' => 1400]);
        ParametrePrix::firstOrCreate(['nom' => 'transport_par_bac'], ['valeur_defaut' => 100]);
    }
}

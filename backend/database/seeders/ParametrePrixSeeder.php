<?php

namespace Database\Seeders;

use App\Models\ParametrePrix;
use Illuminate\Database\Seeder;

class ParametrePrixSeeder extends Seeder
{
    public function run(): void
    {
        ParametrePrix::create(['nom' => 'prix_bac_glace', 'valeur_defaut' => 1400]);
        ParametrePrix::create(['nom' => 'transport_par_bac', 'valeur_defaut' => 100]);
    }
}

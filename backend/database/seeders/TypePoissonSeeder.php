<?php

namespace Database\Seeders;

use App\Models\TypePoisson;
use Illuminate\Database\Seeder;

class TypePoissonSeeder extends Seeder
{
    public function run(): void
    {
        TypePoisson::create(['nom' => 'Ombrine', 'is_default' => true]);
        TypePoisson::create(['nom' => 'Sardine', 'is_default' => false]);
        TypePoisson::create(['nom' => 'Thiof', 'is_default' => false]);
        TypePoisson::create(['nom' => 'Dorade', 'is_default' => false]);
    }
}

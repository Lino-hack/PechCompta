<?php

namespace Database\Seeders;

use App\Models\TypePoisson;
use Illuminate\Database\Seeder;

class TypePoissonSeeder extends Seeder
{
    public function run(): void
    {
        TypePoisson::firstOrCreate(['nom' => 'Ombrine'], ['is_default' => true]);
        TypePoisson::firstOrCreate(['nom' => 'Sardine'], ['is_default' => false]);
        TypePoisson::firstOrCreate(['nom' => 'Thiof'], ['is_default' => false]);
        TypePoisson::firstOrCreate(['nom' => 'Dorade'], ['is_default' => false]);
    }
}

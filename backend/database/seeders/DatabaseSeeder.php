<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@peche.com'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );

        $this->call([
            TypePoissonSeeder::class,
            ParametrePrixSeeder::class,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\ParametrePrix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParametrePrix>
 */
class ParametrePrixFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->word(),
            'valeur_defaut' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}

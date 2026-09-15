<?php

namespace Database\Factories;

use App\Models\TypePoisson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TypePoisson>
 */
class TypePoissonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->word(),
            'is_default' => false,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Pecheur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pecheur>
 */
class PecheurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->firstName(),
        ];
    }
}

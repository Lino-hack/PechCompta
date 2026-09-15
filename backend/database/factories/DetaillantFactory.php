<?php

namespace Database\Factories;

use App\Models\Detaillant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Detaillant>
 */
class DetaillantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
        ];
    }
}

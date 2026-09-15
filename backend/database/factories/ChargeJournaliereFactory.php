<?php

namespace Database\Factories;

use App\Models\ChargeJournaliere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeJournaliere>
 */
class ChargeJournaliereFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->date(),
            'nb_bagues_glace' => fake()->numberBetween(0, 20),
            'prix_bague_utilise' => fake()->randomFloat(2, 0, 2000),
            'transport' => fake()->randomFloat(2, 0, 50000),
        ];
    }
}

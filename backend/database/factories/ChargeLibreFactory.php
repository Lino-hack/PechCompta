<?php

namespace Database\Factories;

use App\Models\ChargeJournaliere;
use App\Models\ChargeLibre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeLibre>
 */
class ChargeLibreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'charge_journaliere_id' => ChargeJournaliere::factory(),
            'libelle' => fake()->word(),
            'montant' => fake()->randomFloat(2, 100, 10000),
        ];
    }
}

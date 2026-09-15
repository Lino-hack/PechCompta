<?php

namespace Database\Factories;

use App\Models\LigneAchat;
use App\Models\SourceAchat;
use App\Models\TypePoisson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LigneAchat>
 */
class LigneAchatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_achat_id' => SourceAchat::factory(),
            'type_poisson_id' => TypePoisson::factory(),
            'poids_kg' => fake()->randomFloat(2, 1, 200),
            'prix' => fake()->randomFloat(2, 100, 50000),
        ];
    }
}

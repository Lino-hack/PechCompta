<?php

namespace Database\Factories;

use App\Models\Detaillant;
use App\Models\Pecheur;
use App\Models\SourceAchat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceAchat>
 */
class SourceAchatFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['pirogue', 'detaillant']);

        return [
            'type' => $type,
            'pecheur_id' => $type === 'pirogue' ? Pecheur::factory() : null,
            'detaillant_id' => $type === 'detaillant' ? Detaillant::factory() : null,
            'date' => fake()->date(),
        ];
    }
}

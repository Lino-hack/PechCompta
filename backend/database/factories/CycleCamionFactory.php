<?php

namespace Database\Factories;

use App\Models\CycleCamion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CycleCamion>
 */
class CycleCamionFactory extends Factory
{
    public function definition(): array
    {
        $dateDebut = fake()->date();

        return [
            'date_debut' => $dateDebut,
            'date_fin' => fake()->dateTimeBetween($dateDebut, '+10 days')->format('Y-m-d'),
            'frais_route' => fake()->randomFloat(2, 0, 100000),
            'statut' => fake()->randomElement(['ouvert', 'cloture']),
        ];
    }
}

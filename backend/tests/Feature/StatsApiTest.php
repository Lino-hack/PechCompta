<?php

namespace Tests\Feature;

use App\Models\ChargeJournaliere;
use App\Models\ChargeLibre;
use App\Models\LigneAchat;
use App\Models\SourceAchat;
use App\Models\TypePoisson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->token = $user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_stats_require_authentication(): void
    {
        $this->getJson('/api/stats')->assertUnauthorized();
    }

    public function test_stats_compute_today_totals(): void
    {
        $type1 = TypePoisson::factory()->create(['nom' => 'Dorade']);
        $type2 = TypePoisson::factory()->create(['nom' => 'Sardine']);

        $source1 = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        $source2 = SourceAchat::factory()->create(['date' => now()->toDateString()]);

        LigneAchat::factory()->create(['source_achat_id' => $source1->id, 'type_poisson_id' => $type1->id, 'poids_kg' => 10, 'prix' => 10000]);
        LigneAchat::factory()->create(['source_achat_id' => $source2->id, 'type_poisson_id' => $type2->id, 'poids_kg' => 5, 'prix' => 7500]);

        $charge = ChargeJournaliere::factory()->create([
            'date' => now()->toDateString(),
            'nb_bagues_glace' => 3,
            'prix_bague_utilise' => 500,
            'transport' => 2000,
        ]);
        ChargeLibre::factory()->create(['charge_journaliere_id' => $charge->id, 'libelle' => 'Nourriture', 'montant' => 1500]);

        $response = $this->getJson('/api/stats', $this->authHeaders())->assertOk();

        $today = $response->json('today');

        $this->assertSame(15.0, (float) $today['achats']['poids_total']);
        $this->assertSame(17500.0, (float) $today['achats']['montant_total']);
        $this->assertSame(2, $today['achats']['nb_sources']);

        // glace 3*500 + transport 2000 + libres 1500 = 5000
        $this->assertSame(1500.0, (float) $today['charges']['frais_glace']);
        $this->assertSame(2000.0, (float) $today['charges']['transport']);
        $this->assertSame(1500.0, (float) $today['charges']['libres_total']);
        $this->assertSame(5000.0, (float) $today['charges']['total']);

        $this->assertSame(22500.0, (float) $today['total_depenses']);

        $parType = collect($today['achats']['par_type'])->keyBy('nom');
        $this->assertSame(10000.0, (float) $parType['Dorade']['montant_total']);
        $this->assertSame(7500.0, (float) $parType['Sardine']['montant_total']);
    }

    public function test_stats_ignore_out_of_period_data(): void
    {
        $oldSource = SourceAchat::factory()->create(['date' => now()->subDays(40)->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $oldSource->id, 'poids_kg' => 999, 'prix' => 999999]);

        $response = $this->getJson('/api/stats', $this->authHeaders())->assertOk();

        $this->assertSame(0.0, (float) $response->json('today.achats.montant_total'));
        $this->assertSame(0.0, (float) $response->json('totaux_periode.montant_achats'));
    }

    public function test_stats_accept_custom_period(): void
    {
        $from = now()->subDays(20)->toDateString();
        $to = now()->subDays(10)->toDateString();

        $inSource = SourceAchat::factory()->create(['date' => $from]);
        LigneAchat::factory()->create(['source_achat_id' => $inSource->id, 'poids_kg' => 10, 'prix' => 10000]);

        $outSource = SourceAchat::factory()->create(['date' => now()->subDays(40)->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $outSource->id, 'poids_kg' => 999, 'prix' => 999999]);

        $response = $this->getJson("/api/stats?from={$from}&to={$to}", $this->authHeaders())->assertOk();

        $this->assertSame(10000.0, (float) $response->json('totaux_periode.montant_achats'));
        $this->assertSame(10.0, (float) $response->json('totaux_periode.poids_total'));
    }
}

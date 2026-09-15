<?php

namespace Tests\Feature;

use App\Models\Detaillant;
use App\Models\LigneAchat;
use App\Models\Pecheur;
use App\Models\SourceAchat;
use App\Models\TypePoisson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchatApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'admin@peche.com',
            'password' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_ligne_achat_is_rejected_without_authentication(): void
    {
        $this->postJson('/api/achats', [])->assertUnauthorized();
    }

    public function test_achat_creates_pecheur_and_source_dynamically(): void
    {
        $type = TypePoisson::factory()->create();

        $response = $this->postJson('/api/achats', [
            'type' => 'pirogue',
            'nom' => 'Moussa Diop',
            'type_poisson_id' => $type->id,
            'poids_kg' => 25.5,
            'prix' => 50000,
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('type_poisson_id', $type->id)
            ->assertJsonPath('poids_kg', 25.5);

        $this->assertDatabaseHas('pecheurs', ['nom' => 'Moussa Diop']);
        $this->assertSame(1, Pecheur::count());
        $this->assertSame(1, SourceAchat::where('date', now()->toDateString())->count());
    }

    public function test_second_ligne_same_day_reuses_source_achat_for_same_pecheur(): void
    {
        $type = TypePoisson::factory()->create();

        $this->postJson('/api/achats', [
            'type' => 'pirogue',
            'nom' => 'Moussa Diop',
            'type_poisson_id' => $type->id,
            'poids_kg' => 10,
            'prix' => 20000,
        ], $this->authHeaders());

        $this->postJson('/api/achats', [
            'type' => 'pirogue',
            'nom' => 'Moussa Diop',
            'type_poisson_id' => $type->id,
            'poids_kg' => 5,
            'prix' => 8000,
        ], $this->authHeaders());

        $this->assertSame(1, SourceAchat::count());
        $this->assertSame(2, LigneAchat::count());
        $this->assertSame(1, Pecheur::count());
    }

    public function test_achat_creates_detaillant_dynamically(): void
    {
        $type = TypePoisson::factory()->create();

        $this->postJson('/api/achats', [
            'type' => 'detaillant',
            'nom' => 'Awa Ndiaye',
            'type_poisson_id' => $type->id,
            'poids_kg' => 3,
            'prix' => 15000,
        ], $this->authHeaders());

        $this->assertDatabaseHas('detaillants', ['nom' => 'Awa Ndiaye']);
        $this->assertSame(1, Detaillant::count());
        $this->assertSame(1, SourceAchat::where('type', 'detaillant')->count());
    }

    public function test_achat_requires_valid_type_and_prix(): void
    {
        $this->postJson('/api/achats', [
            'type' => 'invalide',
            'nom' => 'Test',
            'type_poisson_id' => TypePoisson::factory()->create()->id,
            'poids_kg' => 1,
            'prix' => -5,
        ], $this->authHeaders())->assertUnprocessable();
    }

    public function test_delete_ligne_removes_it(): void
    {
        $ligne = LigneAchat::factory()->create();

        $this->deleteJson('/api/achats/lignes/'.$ligne->id, [], $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseMissing('ligne_achats', ['id' => $ligne->id]);
    }

    public function test_delete_last_ligne_removes_source_achat(): void
    {
        $source = SourceAchat::factory()->create();
        $ligne = LigneAchat::factory()->create(['source_achat_id' => $source->id]);

        $this->deleteJson('/api/achats/lignes/'.$ligne->id, [], $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseMissing('ligne_achats', ['id' => $ligne->id]);
        $this->assertDatabaseMissing('source_achats', ['id' => $source->id]);
    }

    public function test_delete_ligne_keeps_source_achat_when_other_lignes_remain(): void
    {
        $source = SourceAchat::factory()->create();
        $ligne1 = LigneAchat::factory()->create(['source_achat_id' => $source->id]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id]);

        $this->deleteJson('/api/achats/lignes/'.$ligne1->id, [], $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseHas('source_achats', ['id' => $source->id]);
    }

    public function test_update_ligne_modifies_poids_and_prix(): void
    {
        $ligne = LigneAchat::factory()->create();
        $newType = TypePoisson::factory()->create();

        $response = $this->putJson('/api/achats/lignes/'.$ligne->id, [
            'type_poisson_id' => $newType->id,
            'poids_kg' => 30,
            'prix' => 60000,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('poids_kg', 30)
            ->assertJsonPath('prix', 60000)
            ->assertJsonPath('type_poisson_id', $newType->id);

        $this->assertDatabaseHas('ligne_achats', [
            'id' => $ligne->id,
            'poids_kg' => 30,
            'prix' => 60000,
        ]);
    }

    public function test_update_ligne_rejects_invalid_price(): void
    {
        $ligne = LigneAchat::factory()->create();

        $this->putJson('/api/achats/lignes/'.$ligne->id, [
            'type_poisson_id' => $ligne->type_poisson_id,
            'poids_kg' => 5,
            'prix' => -10,
        ], $this->authHeaders())->assertUnprocessable();
    }

    public function test_get_today_returns_only_today_achats(): void
    {
        $today = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        $old = SourceAchat::factory()->create(['date' => now()->subDays(2)->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $today->id]);
        LigneAchat::factory()->create(['source_achat_id' => $old->id]);

        $response = $this->getJson('/api/achats/today', $this->authHeaders())->assertOk();

        $this->assertCount(1, $response->json());
    }

    public function test_get_today_excludes_sources_without_lignes(): void
    {
        SourceAchat::factory()->create(['date' => now()->toDateString()]);
        $withLigne = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $withLigne->id]);

        $response = $this->getJson('/api/achats/today', $this->authHeaders())->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame($withLigne->id, $response->json('0.id'));
    }
}

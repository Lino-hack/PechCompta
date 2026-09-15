<?php

namespace Tests\Feature;

use App\Models\LigneAchat;
use App\Models\TypePoisson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadOnlyUserTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'lecture@peche.com',
            'role' => 'viewer',
        ]);
        $this->token = $user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_viewer_can_read_today_achats(): void
    {
        $this->getJson('/api/achats/today', $this->authHeaders())->assertOk();
    }

    public function test_viewer_can_read_stats(): void
    {
        $this->getJson('/api/stats', $this->authHeaders())->assertOk();
    }

    public function test_viewer_can_read_cycles(): void
    {
        $this->getJson('/api/cycles', $this->authHeaders())->assertOk();
    }

    public function test_viewer_can_export_pdf(): void
    {
        $date = now()->toDateString();

        $this->getJson('/api/export/pdf?from='.$date.'&to='.$date, $this->authHeaders())->assertOk();
    }

    public function test_viewer_cannot_create_achat(): void
    {
        $this->postJson('/api/achats', [
            'type' => 'pirogue',
            'nom' => 'Moussa Diop',
            'type_poisson_id' => TypePoisson::factory()->create()->id,
            'poids_kg' => 25.5,
            'prix' => 50000,
        ], $this->authHeaders())->assertForbidden();
    }

    public function test_viewer_cannot_update_ligne(): void
    {
        $ligne = LigneAchat::factory()->create();

        $this->putJson('/api/achats/lignes/'.$ligne->id, [
            'type_poisson_id' => $ligne->type_poisson_id,
            'poids_kg' => 5,
            'prix' => 5000,
        ], $this->authHeaders())->assertForbidden();
    }

    public function test_viewer_cannot_delete_ligne(): void
    {
        $ligne = LigneAchat::factory()->create();

        $this->deleteJson('/api/achats/lignes/'.$ligne->id, [], $this->authHeaders())->assertForbidden();
    }

    public function test_viewer_cannot_update_charges(): void
    {
        $this->postJson('/api/charges/today', [
            'nb_bagues_glace' => 1,
            'prix_bague_utilise' => 1400,
            'transport' => 100,
            'charges_libres' => [],
        ], $this->authHeaders())->assertForbidden();
    }

    public function test_viewer_cannot_create_cycle(): void
    {
        $this->postJson('/api/cycles', [
            'date_debut' => now()->toDateString(),
            'frais_route' => 0,
        ], $this->authHeaders())->assertForbidden();
    }

    public function test_viewer_cannot_update_parametres(): void
    {
        $this->postJson('/api/referentiels/parametres', [
            'nom' => 'prix_bac_glace',
            'valeur_defaut' => 1500,
        ], $this->authHeaders())->assertForbidden();
    }

    public function test_viewer_can_change_own_password(): void
    {
        $this->postJson('/api/change-password', [
            'current_password' => 'password',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ], $this->authHeaders())->assertOk();
    }

    public function test_admin_can_still_write(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$adminToken];

        $response = $this->postJson('/api/achats', [
            'type' => 'pirogue',
            'nom' => 'Admin Test',
            'type_poisson_id' => TypePoisson::factory()->create()->id,
            'poids_kg' => 10,
            'prix' => 20000,
        ], $headers);

        $response->assertCreated();
        $this->assertDatabaseHas('pecheurs', ['nom' => 'Admin Test']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ChargeJournaliere;
use App\Models\CycleCamion;
use App\Models\LigneAchat;
use App\Models\SourceAchat;
use App\Models\TypePoisson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CycleApiTest extends TestCase
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

    public function test_create_open_cycle(): void
    {
        $response = $this->postJson('/api/cycles', [
            'date_debut' => now()->toDateString(),
            'frais_route' => 25000,
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('statut', 'ouvert')
            ->assertJsonPath('date_fin', null);
    }

    public function test_close_cycle_sets_date_fin_and_statut(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(3)->toDateString(),
            'date_fin' => null,
            'statut' => 'ouvert',
        ]);

        $this->postJson('/api/cycles/'.$cycle->id.'/close', [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('statut', 'cloture')
            ->assertJsonPath('date_fin', now()->toDateString());
    }

    public function test_update_cycle_updates_dates_and_frais_route(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(5)->toDateString(),
            'date_fin' => null,
            'statut' => 'ouvert',
            'frais_route' => 20000,
        ]);

        $this->putJson('/api/cycles/'.$cycle->id, [
            'date_debut' => now()->subDays(4)->toDateString(),
            'date_fin' => now()->toDateString(),
            'frais_route' => 30000,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('date_debut', now()->subDays(4)->toDateString())
            ->assertJsonPath('date_fin', now()->toDateString())
            ->assertJsonPath('frais_route', 30000);

        $this->assertDatabaseHas('cycle_camions', [
            'id' => $cycle->id,
            'statut' => 'cloture',
        ]);
    }

    public function test_update_cycle_replaces_frais_libres(): void
    {
        $cycle = CycleCamion::factory()->create();
        $cycle->fraisLibres()->create(['libelle' => 'Douane', 'montant' => 10000]);

        $this->putJson('/api/cycles/'.$cycle->id, [
            'date_debut' => now()->toDateString(),
            'frais_libres' => [
                ['libelle' => 'Péage', 'montant' => 5000],
            ],
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('frais_libres.0.libelle', 'Péage')
            ->assertJsonPath('frais_libres.0.montant', 5000);

        $this->assertDatabaseHas('cycle_frais_libres', [
            'cycle_camion_id' => $cycle->id,
            'libelle' => 'Péage',
            'montant' => 5000,
        ]);
        $this->assertDatabaseMissing('cycle_frais_libres', ['libelle' => 'Douane']);
    }

    public function test_destroy_cycle_deletes_frais_libres(): void
    {
        $cycle = CycleCamion::factory()->create();
        $cycle->fraisLibres()->create(['libelle' => 'Douane', 'montant' => 10000]);

        $this->deleteJson('/api/cycles/'.$cycle->id, [], $this->authHeaders())->assertNoContent();

        $this->assertDatabaseMissing('cycle_camions', ['id' => $cycle->id]);
        $this->assertDatabaseMissing('cycle_frais_libres', ['cycle_camion_id' => $cycle->id]);
    }

    public function test_cycle_details_compute_correct_totals(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(3)->toDateString(),
            'date_fin' => now()->toDateString(),
            'statut' => 'cloture',
        ]);

        $type1 = TypePoisson::factory()->create();
        $type2 = TypePoisson::factory()->create();

        $source1 = SourceAchat::factory()->create(['date' => now()->subDays(2)->toDateString()]);
        $source2 = SourceAchat::factory()->create(['date' => now()->toDateString()]);

        LigneAchat::factory()->create(['source_achat_id' => $source1->id, 'type_poisson_id' => $type1->id, 'poids_kg' => 10, 'prix' => 10000]);
        LigneAchat::factory()->create(['source_achat_id' => $source1->id, 'type_poisson_id' => $type2->id, 'poids_kg' => 5, 'prix' => 7500]);
        LigneAchat::factory()->create(['source_achat_id' => $source2->id, 'type_poisson_id' => $type2->id, 'poids_kg' => 2, 'prix' => 2500]);

        // Ligne hors cycle
        $outside = SourceAchat::factory()->create(['date' => now()->subDays(10)->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $outside->id, 'type_poisson_id' => $type1->id, 'poids_kg' => 100, 'prix' => 200000]);

        ChargeJournaliere::factory()->create([
            'date' => now()->toDateString(),
            'nb_bagues_glace' => 2,
            'prix_bague_utilise' => 500,
            'transport' => 3000,
        ]);

        $response = $this->getJson('/api/cycles/'.$cycle->id, $this->authHeaders())->assertOk();

        $this->assertCount(2, $response->json('achats'));
        $this->assertCount(1, $response->json('charges'));

        $montantAchats = collect($response->json('achats'))
            ->flatMap(fn ($source) => $source['lignes_achats'])
            ->sum('prix');
        $this->assertSame(20000.0, (float) $montantAchats);
    }

    public function test_cycle_details_uses_today_when_cycle_open(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(2)->toDateString(),
            'date_fin' => null,
        ]);

        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        $ligne = LigneAchat::factory()->create(['source_achat_id' => $source->id]);

        $response = $this->getJson('/api/cycles/'.$cycle->id, $this->authHeaders())->assertOk();

        $this->assertCount(1, $response->json('achats'));
        $this->assertSame($ligne->id, $response->json('achats.0.lignes_achats.0.id'));
    }

    public function test_cycle_export_excel_returns_xlsx_file(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(3)->toDateString(),
            'date_fin' => now()->toDateString(),
            'statut' => 'cloture',
        ]);
        SourceAchat::factory()->create(['date' => now()->toDateString()]);

        $response = $this->get('/api/cycles/'.$cycle->id.'/export', $this->authHeaders());

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    public function test_cycle_export_pdf_returns_pdf_file(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(3)->toDateString(),
            'date_fin' => now()->toDateString(),
            'statut' => 'cloture',
        ]);

        $response = $this->get('/api/cycles/'.$cycle->id.'/export?format=pdf', $this->authHeaders());

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_cycle_export_covers_cycle_period(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(3)->toDateString(),
            'date_fin' => now()->toDateString(),
            'statut' => 'cloture',
        ]);
        SourceAchat::factory()->create(['date' => now()->toDateString()]);
        SourceAchat::factory()->create(['date' => now()->subDays(10)->toDateString()]);

        $response = $this->get('/api/cycles/'.$cycle->id.'/export', $this->authHeaders());
        $response->assertOk();

        $file = $response->baseResponse->getFile();
        $spreadsheet = IOFactory::load($file->getPathname());
        unlink($file->getPathname());

        $content = implode("\n", array_map(
            fn (array $row) => implode(' | ', $row),
            $spreadsheet->getActiveSheet()->toArray()
        ));

        $this->assertStringContainsString($cycle->date_debut, $content);
        $this->assertStringContainsString($cycle->date_fin, $content);
    }

    public function test_cycle_create_stores_heures(): void
    {
        $response = $this->postJson('/api/cycles', [
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->toDateString(),
            'heure_debut' => '06:00',
            'heure_fin' => '13:00',
        ], $this->authHeaders());

        $response->assertCreated();

        $this->assertStringStartsWith('06:00', (string) $response->json('heure_debut'));
        $this->assertStringStartsWith('13:00', (string) $response->json('heure_fin'));
    }

    public function test_update_cycle_updates_heures(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->toDateString(),
            'date_fin' => null,
            'statut' => 'ouvert',
        ]);

        $response = $this->putJson('/api/cycles/'.$cycle->id, [
            'date_debut' => now()->toDateString(),
            'heure_debut' => '05:00',
            'heure_fin' => '13:00',
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertStringStartsWith('05:00', (string) $response->json('heure_debut'));
        $this->assertStringStartsWith('13:00', (string) $response->json('heure_fin'));
    }

    public function test_cycle_details_excludes_lignes_after_heure_fin(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->toDateString(),
            'heure_debut' => '06:00',
            'heure_fin' => '13:00',
            'statut' => 'cloture',
        ]);

        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 10, 'prix' => 10000, 'heure' => '10:00:00']);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 5, 'prix' => 5000, 'heure' => '15:00:00']);

        $response = $this->getJson('/api/cycles/'.$cycle->id, $this->authHeaders())->assertOk();

        $this->assertCount(1, $response->json('achats'));
        $this->assertCount(1, $response->json('achats.0.lignes_achats'));

        $montant = collect($response->json('achats'))
            ->flatMap(fn ($source) => $source['lignes_achats'])
            ->sum('prix');
        $this->assertSame(10000.0, (float) $montant);
    }

    public function test_cycle_details_excludes_lignes_before_heure_debut(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin' => '13:00',
            'statut' => 'cloture',
        ]);

        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 10, 'prix' => 10000, 'heure' => '07:00:00']);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 5, 'prix' => 5000, 'heure' => '09:00:00']);

        $response = $this->getJson('/api/cycles/'.$cycle->id, $this->authHeaders())->assertOk();

        $montant = collect($response->json('achats'))
            ->flatMap(fn ($source) => $source['lignes_achats'])
            ->sum('prix');
        $this->assertSame(5000.0, (float) $montant);
    }

    public function test_cycle_details_includes_boundary_heure(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->toDateString(),
            'heure_debut' => '06:00',
            'heure_fin' => '13:00',
            'statut' => 'cloture',
        ]);

        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 10, 'prix' => 10000, 'heure' => '13:00:00']);

        $response = $this->getJson('/api/cycles/'.$cycle->id, $this->authHeaders())->assertOk();

        $this->assertCount(1, $response->json('achats'));
        $this->assertSame((float) 10000.0, (float) collect($response->json('achats'))
            ->flatMap(fn ($source) => $source['lignes_achats'])
            ->sum('prix'));
    }

    public function test_cycle_details_includes_legacy_lignes_without_heure(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->subDays(2)->toDateString(),
            'date_fin' => now()->toDateString(),
            'heure_fin' => '13:00',
            'statut' => 'cloture',
        ]);

        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 10, 'prix' => 10000, 'heure' => null]);

        $response = $this->getJson('/api/cycles/'.$cycle->id, $this->authHeaders())->assertOk();

        $this->assertCount(1, $response->json('achats'));
    }

    public function test_cycle_export_excludes_lignes_after_heure_fin(): void
    {
        $cycle = CycleCamion::factory()->create([
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->toDateString(),
            'heure_fin' => '13:00',
            'statut' => 'cloture',
        ]);

        $typeMatin = TypePoisson::factory()->create(['nom' => 'Sole']);
        $typeApres = TypePoisson::factory()->create(['nom' => 'Daurade']);

        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'type_poisson_id' => $typeMatin->id, 'poids_kg' => 10, 'prix' => 10000, 'heure' => '10:00:00']);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'type_poisson_id' => $typeApres->id, 'poids_kg' => 5, 'prix' => 5000, 'heure' => '15:00:00']);

        $response = $this->get('/api/cycles/'.$cycle->id.'/export', $this->authHeaders());
        $response->assertOk();

        $file = $response->baseResponse->getFile();
        $spreadsheet = IOFactory::load($file->getPathname());
        unlink($file->getPathname());

        $content = implode("\n", array_map(
            fn (array $row) => implode(' | ', $row),
            $spreadsheet->getActiveSheet()->toArray()
        ));

        $this->assertStringContainsString('Sole', $content);
        $this->assertStringNotContainsString('Daurade', $content);
    }
}

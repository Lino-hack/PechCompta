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
}

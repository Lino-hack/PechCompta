<?php

namespace Tests\Feature;

use App\Models\ChargeJournaliere;
use App\Models\CycleCamion;
use App\Models\CycleFraisLibre;
use App\Models\LigneAchat;
use App\Models\SourceAchat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportApiTest extends TestCase
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

    public function test_exports_require_authentication(): void
    {
        $this->getJson('/api/export/excel')->assertUnauthorized();
        $this->getJson('/api/export/pdf')->assertUnauthorized();
    }

    public function test_excel_export_returns_xlsx_file(): void
    {
        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 12, 'prix' => 30000]);
        ChargeJournaliere::factory()->create([
            'date' => now()->toDateString(),
            'nb_bagues_glace' => 1,
            'prix_bague_utilise' => 500,
            'transport' => 1000,
        ]);

        $response = $this->get('/api/export/excel', $this->authHeaders());

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    public function test_pdf_export_returns_pdf_file(): void
    {
        $source = SourceAchat::factory()->create(['date' => now()->toDateString()]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 12, 'prix' => 30000]);

        $response = $this->get('/api/export/pdf', $this->authHeaders());

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_exports_accept_custom_period(): void
    {
        $from = now()->subDays(5)->toDateString();
        $to = now()->toDateString();

        $this->get('/api/export/excel?from='.$from.'&to='.$to, $this->authHeaders())->assertOk();
    }

    public function test_excel_export_is_single_sheet_with_cycles_section(): void
    {
        $source = SourceAchat::factory()->create([
            'type' => 'pirogue',
            'date' => now()->toDateString(),
        ]);
        LigneAchat::factory()->create(['source_achat_id' => $source->id, 'poids_kg' => 12, 'prix' => 30000]);

        $detaillantSource = SourceAchat::factory()->create([
            'type' => 'detaillant',
            'date' => now()->toDateString(),
        ]);
        LigneAchat::factory()->create(['source_achat_id' => $detaillantSource->id, 'poids_kg' => 5, 'prix' => 15000]);

        ChargeJournaliere::factory()->create([
            'date' => now()->toDateString(),
            'nb_bagues_glace' => 1,
            'prix_bague_utilise' => 500,
            'transport' => 1000,
        ]);
        $cycle = CycleCamion::create([
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->toDateString(),
            'frais_route' => 5000,
            'statut' => 'cloture',
        ]);
        CycleFraisLibre::create([
            'cycle_camion_id' => $cycle->id,
            'libelle' => 'Réparation',
            'montant' => 2000,
        ]);

        $response = $this->get('/api/export/excel', $this->authHeaders());
        $response->assertOk();

        $file = $response->baseResponse->getFile();
        $spreadsheet = IOFactory::load($file->getPathname());
        unlink($file->getPathname());

        $this->assertSame(1, $spreadsheet->getSheetCount());

        $content = implode("\n", array_map(
            fn (array $row) => implode(' | ', $row),
            $spreadsheet->getActiveSheet()->toArray()
        ));

        foreach (['1. Achats pirogues', '2. Achats détaillants', '3. Charges journalières', '4. Cycles camion', '5. Résumé', '7 000 FCFA', '53 500 FCFA'] as $needle) {
            $this->assertStringContainsString($needle, $content);
        }
    }

    public function test_excel_export_without_cycles_omits_cycles_section(): void
    {
        SourceAchat::factory()->create(['date' => now()->toDateString()]);
        CycleCamion::create([
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->toDateString(),
            'frais_route' => 5000,
            'statut' => 'cloture',
        ]);

        $response = $this->get('/api/export/excel?include_cycles=0', $this->authHeaders());
        $response->assertOk();

        $file = $response->baseResponse->getFile();
        $spreadsheet = IOFactory::load($file->getPathname());
        unlink($file->getPathname());

        $content = implode("\n", array_map(
            fn (array $row) => implode(' | ', $row),
            $spreadsheet->getActiveSheet()->toArray()
        ));

        $this->assertStringNotContainsString('4. Cycles camion', $content);
        $this->assertStringContainsString('5. Résumé', $content);
    }
}

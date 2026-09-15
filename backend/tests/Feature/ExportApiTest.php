<?php

namespace Tests\Feature;

use App\Models\ChargeJournaliere;
use App\Models\LigneAchat;
use App\Models\SourceAchat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

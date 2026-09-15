<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChargeJournaliere;
use App\Models\LigneAchat;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $today = now()->toDateString();
        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? $today;

        return response()->json([
            'today' => $this->daySummary($today),
            'series_7j' => $this->lastSevenDays(),
            'totaux_periode' => $this->periodTotals($from, $to),
        ]);
    }

    private function daySummary(string $date): array
    {
        $lignes = LigneAchat::whereHas('sourceAchat', fn ($query) => $query->where('date', $date))->get();
        $charge = ChargeJournaliere::where('date', $date)->with('chargesLibres')->first();

        $parType = LigneAchat::query()
            ->join('source_achats', 'ligne_achats.source_achat_id', '=', 'source_achats.id')
            ->join('type_poissons', 'ligne_achats.type_poisson_id', '=', 'type_poissons.id')
            ->where('source_achats.date', $date)
            ->select('type_poissons.id', 'type_poissons.nom')
            ->selectRaw('SUM(ligne_achats.poids_kg) as poids_total')
            ->selectRaw('SUM(ligne_achats.prix) as montant_total')
            ->groupBy('type_poissons.id', 'type_poissons.nom')
            ->orderByDesc('montant_total')
            ->get();

        $fraisGlace = 0;
        $transport = 0;
        $chargesLibresTotal = 0;

        if ($charge !== null) {
            $fraisGlace = round((float) $charge->nb_bagues_glace * (float) $charge->prix_bague_utilise, 2);
            $transport = round((float) $charge->transport, 2);
            $chargesLibresTotal = round((float) $charge->chargesLibres->sum('montant'), 2);
        }

        $montantAchats = round((float) $lignes->sum('prix'), 2);
        $chargesTotal = round($fraisGlace + $transport + $chargesLibresTotal, 2);

        return [
            'date' => $date,
            'achats' => [
                'nb_sources' => $lignes->pluck('source_achat_id')->unique()->count(),
                'nb_lignes' => $lignes->count(),
                'poids_total' => round((float) $lignes->sum('poids_kg'), 2),
                'montant_total' => $montantAchats,
                'par_type' => $parType,
            ],
            'charges' => [
                'frais_glace' => $fraisGlace,
                'transport' => $transport,
                'libres_total' => $chargesLibresTotal,
                'total' => $chargesTotal,
            ],
            'total_depenses' => round($montantAchats + $chargesTotal, 2),
        ];
    }

    private function lastSevenDays(): array
    {
        return collect(range(6, 0))
            ->map(function (int $offset): array {
                $date = now()->subDays($offset)->toDateString();

                return $this->daySummary($date);
            })
            ->all();
    }

    private function periodTotals(string $from, string $to): array
    {
        $lignes = LigneAchat::whereHas('sourceAchat', fn ($query) => $query->whereBetween('date', [$from, $to]))
            ->get(['poids_kg', 'prix']);

        $charges = ChargeJournaliere::whereBetween('date', [$from, $to])
            ->with('chargesLibres')
            ->get();

        $montantAchats = round((float) $lignes->sum('prix'), 2);
        $poidsTotal = round((float) $lignes->sum('poids_kg'), 2);

        $chargesTotal = round(
            (float) $charges->sum(fn ($charge) => ((float) $charge->nb_bagues_glace * (float) $charge->prix_bague_utilise) + (float) $charge->transport + (float) $charge->chargesLibres->sum('montant')),
            2
        );

        return [
            'from' => $from,
            'to' => $to,
            'poids_total' => $poidsTotal,
            'montant_achats' => $montantAchats,
            'charges_total' => $chargesTotal,
            'total_depenses' => round($montantAchats + $chargesTotal, 2),
        ];
    }
}

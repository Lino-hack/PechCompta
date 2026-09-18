<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChargeJournaliere;
use App\Models\CycleCamion;
use App\Models\LigneAchat;
use App\Models\SourceAchat;
use Illuminate\Http\Request;

class CycleController extends Controller
{
    public function export(Request $request, int $id)
    {
        $cycle = CycleCamion::findOrFail($id);

        $request->merge([
            'from' => $cycle->date_debut,
            'to' => $cycle->date_fin ?? now()->toDateString(),
            'include_cycles' => 1,
            'cycle_heure_debut' => $cycle->heure_debut,
            'cycle_heure_fin' => $cycle->heure_fin,
        ]);

        $export = new ExportController;

        return $request->query('format', 'excel') === 'pdf'
            ? $export->exportPdf($request)
            : $export->exportExcel($request);
    }

    public function index()
    {
        return response()->json(CycleCamion::with('fraisLibres')->orderBy('date_debut', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'heure_debut' => 'nullable|date_format:H:i',
            'heure_fin' => 'nullable|date_format:H:i',
            'frais_route' => 'numeric|min:0',
            'frais_libres' => 'nullable|array',
            'frais_libres.*.libelle' => 'required|string',
            'frais_libres.*.montant' => 'required|numeric|min:0',
        ]);

        $cycle = CycleCamion::create([
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'] ?? null,
            'heure_debut' => $validated['heure_debut'] ?? null,
            'heure_fin' => $validated['heure_fin'] ?? null,
            'frais_route' => $validated['frais_route'] ?? 0,
            'statut' => 'ouvert',
        ]);

        $this->syncFraisLibres($cycle, $validated['frais_libres'] ?? []);

        return response()->json($cycle->load('fraisLibres'), 201);
    }

    public function update(Request $request, int $id)
    {
        $cycle = CycleCamion::findOrFail($id);

        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'heure_debut' => 'nullable|date_format:H:i',
            'heure_fin' => 'nullable|date_format:H:i',
            'frais_route' => 'numeric|min:0',
            'frais_libres' => 'nullable|array',
            'frais_libres.*.libelle' => 'required|string',
            'frais_libres.*.montant' => 'required|numeric|min:0',
        ]);

        $cycle->update([
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'] ?? null,
            'heure_debut' => $validated['heure_debut'] ?? null,
            'heure_fin' => $validated['heure_fin'] ?? null,
            'frais_route' => $validated['frais_route'] ?? $cycle->frais_route,
        ]);

        if (array_key_exists('frais_libres', $validated)) {
            $cycle->fraisLibres()->delete();
            $this->syncFraisLibres($cycle, $validated['frais_libres']);
        }

        if ($cycle->date_fin !== null && $cycle->statut === 'ouvert') {
            $cycle->statut = 'cloture';
            $cycle->save();
        }

        return response()->json($cycle->load('fraisLibres'));
    }

    public function destroy(int $id)
    {
        $cycle = CycleCamion::findOrFail($id);
        $cycle->delete();

        return response()->json(null, 204);
    }

    public function addFraisLibre(Request $request, int $id)
    {
        $cycle = CycleCamion::findOrFail($id);

        $validated = $request->validate([
            'libelle' => 'required|string',
            'montant' => 'required|numeric|min:0',
        ]);

        $frais = $cycle->fraisLibres()->create($validated);

        return response()->json($frais, 201);
    }

    public function destroyFraisLibre(int $id, int $fraisId)
    {
        $cycle = CycleCamion::findOrFail($id);
        $cycle->fraisLibres()->where('id', $fraisId)->delete();

        return response()->json(null, 204);
    }

    private function syncFraisLibres(CycleCamion $cycle, array $fraisLibres): void
    {
        foreach ($fraisLibres as $frais) {
            $cycle->fraisLibres()->create([
                'libelle' => $frais['libelle'],
                'montant' => $frais['montant'],
            ]);
        }
    }

    public function close(Request $request, int $id)
    {
        $cycle = CycleCamion::findOrFail($id);

        $validated = $request->validate([
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'frais_route' => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('frais_route', $validated)) {
            $cycle->frais_route = $validated['frais_route'];
        }

        $cycle->date_fin = $validated['date_fin'] ?? now()->toDateString();
        $cycle->statut = 'cloture';
        $cycle->save();

        return response()->json($cycle);
    }

    public function getDetails($id)
    {
        $cycle = CycleCamion::findOrFail($id);
        $dateFin = $cycle->date_fin ?? now()->toDateString();
        $debut = $cycle->date_debut.' '.(($this->heureSec($cycle->heure_debut)) ?: '00:00:00');
        $fin = $dateFin.' '.(($this->heureSec($cycle->heure_fin)) ?: '23:59:59');

        $achats = SourceAchat::with(['pecheur', 'detaillant', 'lignesAchats.typePoisson'])
            ->whereBetween('date', [$cycle->date_debut, $dateFin])
            ->get()
            ->map(function (SourceAchat $source) use ($debut, $fin): SourceAchat {
                $source->setRelation('lignesAchats', $source->lignesAchats->filter(
                    fn (LigneAchat $ligne) => $source->date.' '.$this->heureSec($ligne->heure) >= $debut
                        && $source->date.' '.$this->heureSec($ligne->heure) <= $fin
                )->values());

                return $source;
            })
            ->filter(fn (SourceAchat $source) => $source->lignesAchats->isNotEmpty())
            ->values();

        $charges = ChargeJournaliere::with('chargesLibres')
            ->whereBetween('date', [$cycle->date_debut, $dateFin])
            ->get();

        return response()->json([
            'cycle' => $cycle->load('fraisLibres'),
            'achats' => $achats,
            'charges' => $charges,
        ]);
    }

    private function heureSec(?string $heure): string
    {
        if ($heure === null || $heure === '') {
            return '00:00:00';
        }

        return strlen($heure) === 5 ? $heure.':00' : $heure;
    }
}

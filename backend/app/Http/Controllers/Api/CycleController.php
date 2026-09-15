<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChargeJournaliere;
use App\Models\CycleCamion;
use App\Models\SourceAchat;
use Illuminate\Http\Request;

class CycleController extends Controller
{
    public function index()
    {
        return response()->json(CycleCamion::with('fraisLibres')->orderBy('date_debut', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'frais_route' => 'numeric|min:0',
            'frais_libres' => 'nullable|array',
            'frais_libres.*.libelle' => 'required|string',
            'frais_libres.*.montant' => 'required|numeric|min:0',
        ]);

        $cycle = CycleCamion::create([
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'] ?? null,
            'frais_route' => $validated['frais_route'] ?? 0,
            'statut' => 'ouvert',
        ]);

        $this->syncFraisLibres($cycle, $validated['frais_libres'] ?? []);

        return response()->json($cycle->load('fraisLibres'), 201);
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
        $achats = SourceAchat::with(['pecheur', 'detaillant', 'lignesAchats.typePoisson'])
            ->whereBetween('date', [$cycle->date_debut, $dateFin])
            ->get();
        $charges = ChargeJournaliere::with('chargesLibres')
            ->whereBetween('date', [$cycle->date_debut, $dateFin])
            ->get();

        return response()->json([
            'cycle' => $cycle->load('fraisLibres'),
            'achats' => $achats,
            'charges' => $charges,
        ]);
    }
}

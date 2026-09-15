<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Detaillant;
use App\Models\LigneAchat;
use App\Models\Pecheur;
use App\Models\SourceAchat;
use Illuminate\Http\Request;

class AchatController extends Controller
{
    public function getTodayAchats(Request $request)
    {
        $today = date('Y-m-d');
        $achats = SourceAchat::with(['pecheur', 'detaillant', 'lignesAchats.typePoisson'])
            ->where('date', $today)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($achats);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:pirogue,detaillant',
            'nom' => 'required|string',
            'type_poisson_id' => 'required|exists:type_poissons,id',
            'poids_kg' => 'required|numeric|min:0',
            'prix' => 'required|numeric|min:0',
        ]);

        $today = date('Y-m-d');
        $sourceId = null;

        if ($validated['type'] === 'pirogue') {
            $pecheur = Pecheur::firstOrCreate(['nom' => $validated['nom']]);
            $source = SourceAchat::firstOrCreate([
                'type' => 'pirogue',
                'pecheur_id' => $pecheur->id,
                'date' => $today,
            ]);
            $sourceId = $source->id;
        } else {
            $detaillant = Detaillant::firstOrCreate(['nom' => $validated['nom']]);
            $source = SourceAchat::firstOrCreate([
                'type' => 'detaillant',
                'detaillant_id' => $detaillant->id,
                'date' => $today,
            ]);
            $sourceId = $source->id;
        }

        $ligne = LigneAchat::create([
            'source_achat_id' => $sourceId,
            'type_poisson_id' => $validated['type_poisson_id'],
            'poids_kg' => $validated['poids_kg'],
            'prix' => $validated['prix'],
        ]);

        return response()->json($ligne->load('typePoisson', 'sourceAchat.pecheur', 'sourceAchat.detaillant'), 201);
    }

    public function updateLigne(Request $request, int $id)
    {
        $validated = $request->validate([
            'type_poisson_id' => 'required|exists:type_poissons,id',
            'poids_kg' => 'required|numeric|min:0',
            'prix' => 'required|numeric|min:0',
        ]);

        $ligne = LigneAchat::findOrFail($id);
        $ligne->update($validated);

        return response()->json($ligne->load('typePoisson', 'sourceAchat.pecheur', 'sourceAchat.detaillant'));
    }

    public function destroyLigne(int $id)
    {
        $ligne = LigneAchat::findOrFail($id);
        $ligne->delete();

        return response()->json(['message' => 'Ligne supprimée']);
    }
}

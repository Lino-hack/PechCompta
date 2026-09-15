<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChargeJournaliere;
use App\Models\ChargeLibre;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function getToday(Request $request)
    {
        $today = date('Y-m-d');
        $charge = ChargeJournaliere::with('chargesLibres')->firstOrCreate(['date' => $today]);

        return response()->json($charge);
    }

    public function updateToday(Request $request)
    {
        $validated = $request->validate([
            'nb_bagues_glace' => 'integer|min:0',
            'prix_bague_utilise' => 'numeric|min:0',
            'transport' => 'numeric|min:0',
            'charges_libres' => 'array',
        ]);

        $today = date('Y-m-d');
        $charge = ChargeJournaliere::firstOrCreate(['date' => $today]);

        $charge->update([
            'nb_bagues_glace' => $validated['nb_bagues_glace'] ?? $charge->nb_bagues_glace,
            'prix_bague_utilise' => $validated['prix_bague_utilise'] ?? $charge->prix_bague_utilise,
            'transport' => $validated['transport'] ?? $charge->transport,
        ]);

        if (isset($validated['charges_libres'])) {
            // Recreate libres charges
            $charge->chargesLibres()->delete();
            foreach ($validated['charges_libres'] as $cl) {
                ChargeLibre::create([
                    'charge_journaliere_id' => $charge->id,
                    'libelle' => $cl['libelle'],
                    'montant' => $cl['montant'],
                ]);
            }
        }

        return response()->json($charge->load('chargesLibres'));
    }
}

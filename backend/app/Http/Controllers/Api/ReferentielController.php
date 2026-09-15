<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Detaillant;
use App\Models\ParametrePrix;
use App\Models\Pecheur;
use App\Models\TypePoisson;
use Illuminate\Http\Request;

class ReferentielController extends Controller
{
    public function getTypesPoisson()
    {
        return response()->json(TypePoisson::all());
    }

    public function storeTypePoisson(Request $request)
    {
        $validated = $request->validate(['nom' => 'required|string']);
        $type = TypePoisson::create(['nom' => $validated['nom'], 'is_default' => false]);

        return response()->json($type, 201);
    }

    public function getParametres()
    {
        return response()->json(ParametrePrix::all());
    }

    public function updateParametre(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string',
            'valeur_defaut' => 'required|numeric',
        ]);

        $param = ParametrePrix::updateOrCreate(
            ['nom' => $validated['nom']],
            ['valeur_defaut' => $validated['valeur_defaut']]
        );

        return response()->json($param);
    }

    public function getPecheurs()
    {
        return response()->json(Pecheur::orderBy('nom')->get());
    }

    public function getDetaillants()
    {
        return response()->json(Detaillant::orderBy('nom')->get());
    }

    public function destroyTypePoisson(int $id)
    {
        $type = TypePoisson::findOrFail($id);
        $type->delete();

        return response()->json(['message' => 'Type de poisson supprimé']);
    }
}

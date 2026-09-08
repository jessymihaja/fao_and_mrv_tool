<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\IndicateurEvolution;
use Illuminate\Http\JsonResponse;

class IndicateurEvolutionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(IndicateurEvolution::all());
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'indicateur_id' => 'required|exists:indicateurs,id',
            'annee'         => 'required|integer|min:2000|max:2100',
            'montant'       => 'required|numeric',
        ]);

        $evolution = IndicateurEvolution::create($validated);

        return response()->json([
            'message' => 'Évolution ajoutée avec succès',
            'data'    => $evolution
        ], 201);
    }

    // Mettre à jour une ligne d'évolution
    public function update(Request $request, int $id): JsonResponse
    {
        $evolution = IndicateurEvolution::findOrFail($id);

        $validated = $request->validate([
            'annee'   => 'sometimes|required|integer|min:2000|max:2100',
            'montant' => 'sometimes|required|numeric',
        ]);

        $evolution->update($validated);

        return response()->json([
            'message' => 'Évolution mise à jour',
            'data'    => $evolution
        ]);
    }

    // Supprimer une ligne
    public function destroy(int $id): JsonResponse
    {
        $evolution = IndicateurEvolution::findOrFail($id);
        $evolution->delete();

        return response()->json(['message' => 'Évolution supprimée']);
    }
}

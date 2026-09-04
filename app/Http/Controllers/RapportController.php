<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\CurrencyAggregator;
use Illuminate\Http\JsonResponse;

class RapportController extends Controller
{
    public function byProject(int $projectId): JsonResponse
    {
        $project = Project::with([
            'province', 'region', 'district', 'commune', 'fokontany',
            'financements', 'documents',
        ])->findOrFail($projectId);

        return response()->json([
            'project'            => new ProjectResource($project),
            'financements_count' => $project->financements->count(),
            'documents_count'    => $project->documents->count(),
            // Corrections : (1) Financement n'a pas de colonne 'montant'
            // (c'est 'budget_approuve') — le sum() renvoyait silencieusement
            // 0 auparavant ; (2) plusieurs devises possibles, donc
            // ventilation par devise au lieu d'un total unique mélangé.
            'budget_total'       => CurrencyAggregator::sumByDevise($project->financements, 'budget_approuve'),
            'generated_at'       => now()->toDateTimeString(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $project = Project::with([
            'province', 'region', 'district', 'commune', 'fokontany',
            'financements', 'documents',
        ])->findOrFail($id);

        // Correction : le champ sommé était 'montant' (inexistant sur
        // Financement, qui utilise 'budget_approuve') — ces 3 totaux
        // renvoyaient donc toujours 0 auparavant.
        $budgetParDevise = CurrencyAggregator::sumByDevise($project->financements, 'budget_approuve');

        $rapport = [
            'projet'  => new ProjectResource($project),
            'resume'  => [
                'nb_financements'  => $project->financements->count(),
                'nb_documents'     => $project->documents->count(),
                'budget_total_ar'  => $budgetParDevise['AR']  ?? 0.0,
                'budget_total_usd' => $budgetParDevise['USD'] ?? 0.0,
                'budget_total_eur' => $budgetParDevise['EUR'] ?? 0.0,
            ],
            'generated_at' => now()->toDateTimeString(),
            'generated_by' => auth()->user()?->name ?? 'Système',
        ];

        return response()->json($rapport);
    }

    public function exportPdf(int $id)
    {
        $project = Project::with([
            'province', 'region', 'district', 'financements', 'documents',
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rapports.pdf', compact('project'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("rapport-projet-{$project->id}.pdf");
    }

    public function exportExcel(int $id): JsonResponse
    {
        // À implémenter avec maatwebsite/excel
        return response()->json(['message' => 'Export Excel en cours de développement.'], 501);
    }
}

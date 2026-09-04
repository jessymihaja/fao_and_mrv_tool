<?php

namespace App\Http\Controllers;

use App\Models\ProjectIdea;
use App\Models\ProjectIdeaStatusHistory;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workflow de l'idée de projet :
 *   Brouillon → Soumis → En étude → Approuvé → Converti en Projet
 *
 * Seules les transitions vers l'étape suivante immédiate sont autorisées
 * (voir ProjectIdea::canTransitionTo) — "Converti en Projet" n'est jamais
 * positionné ici, uniquement par ProjectIdeaConversionController. Chaque
 * changement est historisé dans project_idea_status_history.
 */
class ProjectIdeaStatusController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    private const STATUT_LABELS = [
        'brouillon' => 'Brouillon',
        'soumis'    => 'Soumis',
        'en_etude'  => 'En étude',
        'approuve'  => 'Approuvé',
        'converti'  => 'Converti en Projet',
    ];

    public function update(Request $request, int $id): JsonResponse
    {
        $idea = ProjectIdea::findOrFail($id);

        $validated = $request->validate([
            'nouveau_statut' => 'required|in:brouillon,soumis,en_etude,approuve',
            'commentaire'    => 'nullable|string',
        ]);

        if (! $idea->canTransitionTo($validated['nouveau_statut'])) {
            $depuis = self::STATUT_LABELS[$idea->statut] ?? $idea->statut;
            $vers   = self::STATUT_LABELS[$validated['nouveau_statut']] ?? $validated['nouveau_statut'];

            return response()->json([
                'message' => "Transition invalide : « {$depuis} » ne peut pas passer directement à « {$vers} ». Suivez l'ordre du workflow.",
            ], 422);
        }

        $ancienStatut = $idea->statut;
        $idea->update(['statut' => $validated['nouveau_statut']]);

        ProjectIdeaStatusHistory::create([
            'project_idea_id' => $idea->id,
            'ancien_statut'   => $ancienStatut,
            'nouveau_statut'  => $validated['nouveau_statut'],
            'commentaire'     => $validated['commentaire'] ?? null,
            'changed_by'      => $request->user()?->id,
        ]);

        $this->logService->log(
            'update', 'project_idea_status',
            "Statut de l'idée « {$idea->titre} » : {$ancienStatut} → {$validated['nouveau_statut']}",
            null
        );

        return response()->json($idea->fresh()->load(['statusHistory.auteur']));
    }
}

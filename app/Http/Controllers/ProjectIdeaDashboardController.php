<?php

namespace App\Http\Controllers;

use App\Models\ProjectIdea;
use Illuminate\Http\JsonResponse;

class ProjectIdeaDashboardController extends Controller
{
    private const STATUT_LABELS = [
        'brouillon' => 'Brouillon',
        'soumis'    => 'Soumis',
        'en_etude'  => 'En étude',
        'approuve'  => 'Approuvé',
        'converti'  => 'Converti en projet',
    ];

    public function index(): JsonResponse
    {
        $ideas = ProjectIdea::with(['secteurs', 'financements.organismeContributeur', 'region'])->get();

        $parStatut = collect(ProjectIdea::WORKFLOW)->mapWithKeys(fn ($statut) => [
            $statut => $ideas->where('statut', $statut)->count(),
        ]);

        $budgetTotalEstime = (float) $ideas->sum('budget_total_estime');

        // Budget par secteur (une idée peut couvrir plusieurs secteurs : le
        // budget total de l'idée est comptabilisé pour chacun de ses secteurs)
        $budgetParSecteur = [];
        foreach ($ideas as $idea) {
            foreach ($idea->secteurs as $secteur) {
                $budgetParSecteur[$secteur->designation] = ($budgetParSecteur[$secteur->designation] ?? 0) + (float) $idea->budget_total_estime;
            }
        }
        arsort($budgetParSecteur);

        // Budget par bailleur envisagé (somme des montants demandés par bailleur)
        $budgetParBailleur = [];
        foreach ($ideas as $idea) {
            foreach ($idea->financements as $f) {
                $label = $f->bailleur_label;
                $budgetParBailleur[$label] = ($budgetParBailleur[$label] ?? 0) + (float) ($f->montant_demande ?? 0);
            }
        }
        arsort($budgetParBailleur);

        // Répartition par région
        $parRegion = [];
        foreach ($ideas as $idea) {
            $label = $idea->region?->nom ?? 'Non renseignée';
            $parRegion[$label] = ($parRegion[$label] ?? 0) + 1;
        }
        arsort($parRegion);

        return response()->json([
            'total'                => $ideas->count(),
            'par_statut'           => $parStatut,
            'par_statut_labels'    => collect(self::STATUT_LABELS),
            'budget_total_estime'  => $budgetTotalEstime,
            'budget_par_secteur'   => collect($budgetParSecteur)->map(fn ($v, $k) => ['secteur' => $k, 'montant' => $v])->values(),
            'budget_par_bailleur'  => collect($budgetParBailleur)->map(fn ($v, $k) => ['bailleur' => $k, 'montant' => $v])->values(),
            'repartition_region'   => collect($parRegion)->map(fn ($v, $k) => ['region' => $k, 'total' => $v])->values(),
            'repartition_statut'   => $parStatut->map(fn ($v, $k) => ['statut' => self::STATUT_LABELS[$k] ?? $k, 'total' => $v])->values(),
        ]);
    }
}

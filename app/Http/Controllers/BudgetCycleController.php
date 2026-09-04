<?php

namespace App\Http\Controllers;

use App\Models\BudgetApprobation;
use App\Models\BudgetPledge;
use App\Models\Decaissement;
use App\Models\DecaissementPlan;
use App\Models\Depense;
use App\Models\Engagement;
use App\Models\Financement;
use App\Models\FinancementContribution;
use App\Models\Project;
use App\Support\CurrencyAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Vue consolidée du cycle de vie budgétaire (module Budgets) :
 *
 *   Annoncé → Mobilisé → Engagé → Approuvé → Programmé → Décaissé → Audité/Dépensé
 *
 * Agrège les 7 étapes, déjà réparties sur plusieurs tables (BudgetPledge,
 * FinancementContribution, Engagement, BudgetApprobation, DecaissementPlan,
 * Decaissement, Depense), pour alimenter le tableau de bord, la
 * chronologie et le graphique en cascade de l'onglet "Budgets" du projet.
 * Contrôleur en lecture seule : la saisie se fait via les contrôleurs
 * dédiés à chaque étape.
 */
class BudgetCycleController extends Controller
{
    // GET /projects/{id}/budget-cycle?financement_id=&composante_id=&activite_id=
    public function forProject(Request $request, int $projectId): JsonResponse
    {
        Project::findOrFail($projectId);

        $financementsQuery = Financement::where('project_id', $projectId);
        if ($request->filled('financement_id')) {
            $financementsQuery->where('id', $request->integer('financement_id'));
        }
        $financements = $financementsQuery->orderBy('type_financement')->get();

        return response()->json($this->buildResponse(
            $financements->pluck('id')->all(),
            $this->financementsMeta($financements),
            $request->filled('composante_id') ? $request->integer('composante_id') : null,
            $request->filled('activite_id') ? $request->integer('activite_id') : null,
        ));
    }

    // GET /financements/{id}/budget-cycle?composante_id=&activite_id=
    public function forFinancement(Request $request, int $financementId): JsonResponse
    {
        $financement = Financement::findOrFail($financementId);

        return response()->json($this->buildResponse(
            [$financement->id],
            $this->financementsMeta(collect([$financement])),
            $request->filled('composante_id') ? $request->integer('composante_id') : null,
            $request->filled('activite_id') ? $request->integer('activite_id') : null,
        ));
    }

    private function financementsMeta(Collection $financements): Collection
    {
        return $financements->map(fn (Financement $f) => [
            'id'                 => $f->id,
            'type_financement'   => $f->type_financement,
            'source_financement' => $f->source_financement,
            'budget_approuve'    => $f->budget_approuve !== null ? (float) $f->budget_approuve : null,
            'devise'             => $f->devise,
        ])->values();
    }

    private function buildResponse(array $financementIds, Collection $financementsMeta, ?int $composanteId, ?int $activiteId): array
    {
        if (empty($financementIds)) {
            $financementIds = [0]; // aucun financement → toutes les étapes resteront vides
        }

        $scope = function ($query) use ($financementIds, $composanteId, $activiteId) {
            $query->whereIn('financement_id', $financementIds);
            if ($composanteId) {
                $query->where('composante_id', $composanteId);
            }
            if ($activiteId) {
                $query->where('activite_id', $activiteId);
            }

            return $query;
        };

        $pledges       = $scope(BudgetPledge::with(['bailleur', 'composante', 'activite']))->orderBy('date_annonce')->get();
        $mobilisations = $scope(FinancementContribution::with(['organismeContributeur', 'composante', 'activite']))->orderBy('date_contribution')->get();
        $engagements   = $scope(Engagement::with(['bailleur', 'composante', 'activite']))->orderBy('date')->get();
        $approbations  = $scope(BudgetApprobation::with(['organisme', 'composante', 'activite']))->orderBy('date_approbation')->get();
        $plans         = $scope(DecaissementPlan::with(['composante', 'activite']))->orderBy('date_prevue')->get();
        $decaissements = $scope(Decaissement::with(['composante', 'activite']))->orderBy('date')->get();
        $depenses      = $scope(Depense::with(['composante', 'activite']))->orderBy('date')->get();

        // Correction multidevises (cahier des charges §7-8-11) : chaque
        // étape peut mélanger des montants en AR, USD, EUR — on ne fait
        // donc plus JAMAIS un ->sum() global, mais un regroupement par
        // devise via CurrencyAggregator. Ex. si Annoncé = 500 USD + 100 AR,
        // $totalPledge = ['AR' => 100.0, 'USD' => 500.0], jamais 600.
        $totalPledge    = CurrencyAggregator::sumByDevise($pledges, 'montant');
        $totalMobilise  = CurrencyAggregator::sumByDevise($mobilisations, 'montant');
        $totalEngage    = CurrencyAggregator::sumByDevise($engagements, 'montant');
        $totalApprouve  = CurrencyAggregator::sumByDevise($approbations, 'montant_approuve');
        $totalProgramme = CurrencyAggregator::sumByDevise($plans, 'montant_prevu');
        $totalDecaisse  = CurrencyAggregator::sumByDevise($decaissements, 'montant');
        $totalDepense   = CurrencyAggregator::sumByDevise($depenses, 'montant');
        $totalAudite    = CurrencyAggregator::sumByDevise($depenses->where('statut', 'audite'), 'montant_audite');

        $stages = [
            'pledge' => [
                'label'  => 'Annoncé (Pledge)',
                'totaux' => $totalPledge,
                'count'  => $pledges->count(),
                'items'  => $pledges->map(fn ($i) => $this->mapItem('pledge', $i))->values(),
            ],
            'mobilise' => [
                'label'  => 'Mobilisé',
                'totaux' => $totalMobilise,
                'count'  => $mobilisations->count(),
                'items'  => $mobilisations->map(fn ($i) => $this->mapItem('mobilise', $i))->values(),
            ],
            'engage' => [
                'label'  => 'Engagé',
                'totaux' => $totalEngage,
                'count'  => $engagements->count(),
                'items'  => $engagements->map(fn ($i) => $this->mapItem('engage', $i))->values(),
            ],
            'approuve' => [
                'label'  => 'Approuvé',
                'totaux' => $totalApprouve,
                'count'  => $approbations->count(),
                'items'  => $approbations->map(fn ($i) => $this->mapItem('approuve', $i))->values(),
            ],
            'programme' => [
                'label'  => 'Programmé',
                'totaux' => $totalProgramme,
                'count'  => $plans->count(),
                'items'  => $plans->map(fn ($i) => $this->mapItem('programme', $i))->values(),
            ],
            'decaisse' => [
                'label'  => 'Décaissé',
                'totaux' => $totalDecaisse,
                'count'  => $decaissements->count(),
                'items'  => $decaissements->map(fn ($i) => $this->mapItem('decaisse', $i))->values(),
            ],
            'audite' => [
                'label'         => 'Dépensé / Audité',
                'totaux'        => $totalDepense,
                'totaux_audite' => $totalAudite,
                'count'         => $depenses->count(),
                'items'         => $depenses->map(fn ($i) => $this->mapItem('audite', $i))->values(),
            ],
        ];

        // Toutes les devises apparaissant à une étape quelconque du cycle —
        // sert au frontend pour savoir combien de séries tracer sur le
        // graphique en cascade et le récapitulatif, sans deviner à l'avance
        // quelles devises sont utilisées par ce projet/financement.
        $devisesPresentes = collect([$totalPledge, $totalMobilise, $totalEngage, $totalApprouve, $totalProgramme, $totalDecaisse, $totalDepense])
            ->flatMap(fn ($t) => array_keys($t))
            ->unique()
            ->sort()
            ->values();

        return [
            'financements' => $financementsMeta,
            'devises'      => $devisesPresentes,
            'stages'       => $stages,
            'rates'        => [
                'taux_mobilisation' => CurrencyAggregator::rateByDevise($totalMobilise, $totalPledge),
                'taux_engagement'   => CurrencyAggregator::rateByDevise($totalEngage, $totalMobilise),
                'taux_decaissement' => CurrencyAggregator::rateByDevise($totalDecaisse, $totalEngage),
                'taux_execution'    => CurrencyAggregator::rateByDevise($totalDepense, $totalDecaisse),
            ],
            // Un point par étape, avec le détail par devise (jamais un
            // montant unique mélangeant les devises) : ['stage' => 'Annoncé',
            // 'totaux' => ['AR' => 100.0, 'USD' => 500.0]].
            'cascade' => [
                ['stage' => 'Annoncé',   'totaux' => $totalPledge],
                ['stage' => 'Mobilisé',  'totaux' => $totalMobilise],
                ['stage' => 'Engagé',    'totaux' => $totalEngage],
                ['stage' => 'Approuvé',  'totaux' => $totalApprouve],
                ['stage' => 'Programmé', 'totaux' => $totalProgramme],
                ['stage' => 'Décaissé',  'totaux' => $totalDecaisse],
                ['stage' => 'Dépensé',   'totaux' => $totalDepense],
            ],
        ];
    }

    private function mapItem(string $stage, $item): array
    {
        return match ($stage) {
            'pledge' => [
                'id'               => $item->id,
                'date'             => optional($item->date_annonce)->format('Y-m-d'),
                'montant'          => (float) $item->montant,
                'devise'           => $item->devise,
                'label'            => $item->bailleur?->designation ?? $item->source ?? 'Annonce',
                'statut'           => 'complete',
                'has_justificatif' => (bool) $item->justificatif_path,
                'composante_id'    => $item->composante_id,
                'activite_id'      => $item->activite_id,
                'description'      => $item->description,
                'source'           => $item->source,
                'bailleur_id'      => $item->bailleur_id,
                'bailleur'         => $item->bailleur?->designation,
            ],
            'mobilise' => [
                'id'                => $item->id,
                'date'              => optional($item->date_contribution)->format('Y-m-d'),
                'montant'           => (float) $item->montant,
                'devise'            => $item->devise,
                'label'             => $item->organismeContributeur?->designation ?? 'Contribution',
                'statut'            => 'complete',
                'has_justificatif'  => (bool) $item->justificatif_path,
                'composante_id'     => $item->composante_id,
                'activite_id'       => $item->activite_id,
                'organisme_contributeur_id' => $item->organisme_contributeur_id,
                'categorie_contribution_id' => $item->categorie_contribution_id,
                'description'       => $item->description,
                'type_mobilisation' => $item->type_mobilisation,
                'mode_contribution' => $item->mode_contribution,
                'commentaire'       => $item->commentaire,
            ],
            'engage' => [
                'id'               => $item->id,
                'date'             => optional($item->date)->format('Y-m-d'),
                'montant'          => (float) $item->montant,
                'devise'           => $item->devise,
                'label'            => $item->reference_accord ?: ('Accord #' . $item->id),
                'statut'           => 'complete',
                'has_justificatif' => (bool) $item->justificatif_path,
                'composante_id'    => $item->composante_id,
                'activite_id'      => $item->activite_id,
                'reference_accord' => $item->reference_accord,
                'bailleur_id'      => $item->bailleur_id,
                'bailleur'         => $item->bailleur?->designation,
                'description'      => $item->description,
            ],
            'approuve' => [
                'id'               => $item->id,
                'date'             => optional($item->date_approbation)->format('Y-m-d'),
                'montant'          => (float) $item->montant_approuve,
                'devise'           => $item->devise,
                'label'            => $item->organisme?->designation ?? 'Approbation',
                'statut'           => 'complete',
                'has_justificatif' => (bool) $item->justificatif_path,
                'composante_id'    => $item->composante_id,
                'activite_id'      => $item->activite_id,
                'organisme_id'     => $item->organisme_id,
                'reference'        => $item->reference,
                'decision'         => $item->decision,
            ],
            'programme' => [
                'id'                  => $item->id,
                'date'                => optional($item->date_prevue)->format('Y-m-d'),
                'montant'             => (float) $item->montant_prevu,
                'devise'              => $item->devise,
                'label'               => $item->exercice_budgetaire ?: ($item->annee ? (string) $item->annee : 'Programmation'),
                'statut'              => $item->statut === 'effectue' ? 'realise' : 'planifie',
                'has_justificatif'    => (bool) $item->justificatif_path,
                'composante_id'       => $item->composante_id,
                'activite_id'         => $item->activite_id,
                'exercice_budgetaire' => $item->exercice_budgetaire,
                'annee'               => $item->annee,
                'description'         => $item->description,
            ],
            'decaisse' => [
                'id'               => $item->id,
                'date'             => optional($item->date)->format('Y-m-d'),
                'montant'          => (float) $item->montant,
                'devise'           => $item->devise,
                'label'            => $item->beneficiaire ?: ($item->reference ?: ('Décaissement #' . $item->id)),
                'statut'           => 'complete',
                'has_justificatif' => (bool) $item->justificatif_path,
                'composante_id'    => $item->composante_id,
                'activite_id'      => $item->activite_id,
                'reference'        => $item->reference,
                'beneficiaire'     => $item->beneficiaire,
                'commentaire'      => $item->commentaire,
            ],
            'audite' => [
                'id'                => $item->id,
                'date'              => optional($item->date)->format('Y-m-d'),
                'montant'           => (float) $item->montant,
                'devise'            => $item->devise,
                'label'             => $item->designation,
                'statut'            => $item->statut,
                'has_justificatif'  => (bool) $item->justification_path,
                'composante_id'     => $item->composante_id,
                'activite_id'       => $item->activite_id,
                'beneficiaire'      => $item->beneficiaire,
                'categorie'         => $item->categorie,
                'montant_audite'    => $item->montant_audite !== null ? (float) $item->montant_audite : null,
                'organisme_audit'   => $item->organisme_audit,
                'date_audit'        => optional($item->date_audit)->format('Y-m-d'),
                'has_rapport_audit' => (bool) $item->rapport_audit_path,
                'observation_audit' => $item->observation_audit,
            ],
            default => [],
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RapportNational;
use App\Models\Project;
use App\Models\Indicateur;
use App\Support\CurrencyAggregator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RapportNationalController extends Controller
{
    // ── GET /rapports-nationaux ────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $items = RapportNational::with('region:id,nom')
            ->when($request->annee,  fn($q, $v) => $q->where('annee', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 12);

        return response()->json($items);
    }

    // ── POST /rapports-nationaux ───────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'              => 'required|string|max:255',
            'annee'              => 'nullable|integer|min:2000|max:2100',
            'region_id'          => 'nullable|exists:regions,id',
            'secteur_climatique' => 'nullable|string',
            'accredited_entity'  => 'nullable|string|max:255',
            'source_financement' => 'nullable|string|max:255',
            'statut_projet'      => 'nullable|string',
        ]);

        $rapport = RapportNational::create(array_merge($validated, [
            'statut'     => 'brouillon',
            'created_by' => $request->user()->id,
        ]));

        return response()->json($rapport, 201);
    }

    // ── GET /rapports-nationaux/{id} ──────────────────────────────
    public function show(RapportNational $rapportNational): JsonResponse
    {
        return response()->json($rapportNational->load('region:id,nom'));
    }

    // ── POST /rapports-nationaux/{id}/generate ────────────────────
    public function generate(RapportNational $rapportNational): JsonResponse
    {
        $contenu = $this->buildContenu($rapportNational);

        $rapportNational->update([
            'contenu' => $contenu,
            'statut'  => 'genere',
        ]);

        return response()->json($rapportNational->fresh());
    }

    // ── DELETE /rapports-nationaux/{id} ───────────────────────────
    public function destroy(RapportNational $rapportNational): JsonResponse
    {
        $rapportNational->delete();
        return response()->json(null, 204);
    }

    // ── Export PDF (URL signée renvoyée en header) ────────────────
    public function exportPdf(RapportNational $rapportNational)
    {
        // Implémentation avec barryvdh/laravel-dompdf ou Snappy
        // Exemple minimal : retourner une vue compilée en PDF
        // $pdf = \PDF::loadView('rapports.national', ['rapport' => $rapportNational]);
        // return $pdf->download("{$rapportNational->titre}.pdf");

        // Pour l'instant : JSON avec les données brutes
        return response()->json($rapportNational->contenu);
    }

    // ── Export Excel ──────────────────────────────────────────────
    public function exportExcel(RapportNational $rapportNational)
    {
        // Implémentation avec maatwebsite/excel
        // return Excel::download(new RapportNationalExport($rapportNational), 'rapport.xlsx');

        return response()->json($rapportNational->contenu);
    }

    // ─────────────────────────────────────────────────────────────
    // MOTEUR DE GÉNÉRATION
    // ─────────────────────────────────────────────────────────────
    private function buildContenu(RapportNational $r): array
    {
        // ── 1. Construire la requête projets filtrée ──────────────
        $projectsQ = Project::query()
            ->when($r->annee, fn($q, $v) =>
                $q->where(fn($s) =>
                    $s->whereYear('date_debut', $v)
                      ->orWhereYear('date_fin',   $v)
                )
            )
            ->when($r->region_id,          fn($q, $v) => $q->where('region_id', $v))
            ->when($r->secteur_climatique, fn($q, $v) => $q->where('secteur_climatique', $v))
            ->when($r->accredited_entity,  fn($q, $v) => $q->where('accredited_entity', $v))
            ->when($r->statut_projet,      fn($q, $v) => $q->where('statut', $v));

        $projects   = $projectsQ->with('financements')->get();
        $projectIds = $projects->pluck('id');

        // ── 2. Financements ───────────────────────────────────────
        $financements = \App\Models\Financement::whereIn('project_id', $projectIds)
            ->when($r->source_financement, fn($q, $v) => $q->where('source_financement', $v))
            ->get();

        // Correction multidevises (§7-11-14) : un financement peut être en
        // AR, USD ou EUR — plus de sum() global. Chaque total est désormais
        // { devise => montant }.
        $budgetApprouve = CurrencyAggregator::sumByDevise($financements, 'budget_approuve');

        // Engagements et décaissements agrégés (tables existantes)
        $financementIds = $financements->pluck('id');
        $engage   = CurrencyAggregator::sumByDeviseQuery(
            DB::table('engagements')->whereIn('financement_id', $financementIds), 'montant'
        );
        $decaisse = CurrencyAggregator::sumByDeviseQuery(
            DB::table('decaissements')->whereIn('financement_id', $financementIds), 'montant'
        );

        // Taux d'exécution calculé devise par devise (jamais décaissé USD /
        // approuvé AR).
        $txExec = CurrencyAggregator::rateByDevise($decaisse, $budgetApprouve, 2);

        // ── 3. Indicateurs ────────────────────────────────────────
        $indicateurs = Indicateur::whereIn('project_id', $projectIds)
            ->when($r->annee, fn($q, $v) => $q->whereYear('date_reference', $v))
            ->get();

        $physique = $indicateurs->where('categorie', 'physique');
        $adapt    = $indicateurs->where('categorie', 'adaptation');
        $attenu   = $indicateurs->where('categorie', 'attenuation');

        // ── 4. Répartition financière ─────────────────────────────
        // Correction multidevises : chaque secteur/région peut cumuler des
        // financements dans plusieurs devises → ventilation par devise
        // (`totaux`) au lieu d'un `montant` unique qui les mélangeait.
        $parSecteur = $projects
            ->groupBy('secteur_climatique')
            ->map(fn ($g, $secteur) => [
                'secteur' => $secteur,
                'totaux'  => CurrencyAggregator::sumByDevise($g->flatMap->financements, 'budget_approuve'),
            ])
            ->values();

        $parRegion = $projects
            ->groupBy('region_id')
            ->map(fn ($g, $rId) => [
                'region' => $g->first()->region?->nom ?? "Région #{$rId}",
                'totaux' => CurrencyAggregator::sumByDevise($g->flatMap->financements, 'budget_approuve'),
            ])
            ->values();

        // Le classement "top projets" ne peut pas trier sur une somme
        // multidevise (§10 : aucune conversion automatique) ; on trie donc
        // sur le plus gros montant, quelle que soit sa devise, ce qui reste
        // un indicateur raisonnable de "taille" sans jamais additionner
        // deux devises entre elles.
        $topProjets = $projects
            ->sortByDesc(fn ($p) => $p->financements->max('budget_approuve') ?? 0)
            ->take(10)
            ->map(fn ($p) => [
                'titre'  => $p->titre,
                'totaux' => CurrencyAggregator::sumByDevise($p->financements, 'budget_approuve'),
                'taux'   => 0, // à enrichir si décaissements par projet disponibles
            ])
            ->values();

        // ── 5. Agréger les KPIs physiques/climatiques ────────────
        // Utilise le champ `nom` avec des mots-clés pour regrouper
        // (à adapter selon la nomenclature réelle de l'AND)
        $sumInd = fn($collection, string $keyword) =>
            $collection
                ->filter(fn($i) => str_contains(strtolower($i->nom), $keyword))
                ->sum('valeur_realisee');

        return [
            'resume' => [
                'total_projets'         => $projects->count(),
                'budget_total_approuve' => $budgetApprouve,
                'budget_engage'         => $engage,
                'budget_decaisse'       => $decaisse,
                'taux_execution_global' => $txExec,
            ],
            'financier' => [
                'par_secteur' => $parSecteur,
                'par_region'  => $parRegion,
                'top_projets' => $topProjets,
            ],
            'physique' => [
                'total_beneficiaires'      => (int) $sumInd($physique, 'bénéficiaire'),
                'infrastructures_realisees'=> (int) $sumInd($physique, 'infrastructure'),
                'surfaces_restaurees'      => (float) $sumInd($physique, 'surface'),
                'formations_realisees'     => (int) $sumInd($physique, 'formation'),
            ],
            'climatique' => [
                'adaptation' => [
                    'population_resiliente'  => (int) $sumInd($adapt, 'population'),
                    'reduction_vulnerabilite'=> (float) $sumInd($adapt, 'vulnérabilité'),
                    'systemes_alerte'        => (int) $sumInd($adapt, 'alerte'),
                ],
                'attenuation' => [
                    'co2_evite'             => (float) $sumInd($attenu, 'co2'),
                    'energie_renouvelable'   => (float) $sumInd($attenu, 'énergie'),
                    'reduction_energetique'  => (float) $sumInd($attenu, 'réduction'),
                ],
            ],
        ];
    }
}

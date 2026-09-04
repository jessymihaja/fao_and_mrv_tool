<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Decaissement;
use App\Models\Depense;
use App\Models\Document;
use App\Models\Engagement;
use App\Models\Financement;
use App\Models\FinancementContribution;
use App\Models\Indicateur;
use App\Models\Project;
use App\Models\ProjectPerspective;
use App\Models\User;
use App\Support\CurrencyAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;  // Gardé uniquement pour updateManual (public_settings sans modèle)

class StatsController extends Controller
{
    // ── PUBLIC ────────────────────────────────────────────────────────────────
    // Homepage "Impact en chiffres" : colonne 1 (Financements GCF, comportement
    // inchangé) + colonne 2 (Autres financements, nouveau) + date de dernière
    // mise à jour, calculée dynamiquement (pas de champ stocké à maintenir).
    public function public(): JsonResponse
    {
        $publishedProjects = Project::where('is_published', true);

        return response()->json([
            // ── Colonne 1 : Financements GCF (inchangé) ─────────────────────
            'total_projets'      => (clone $publishedProjects)->count(),
            'projets_actifs'     => (clone $publishedProjects)->where('statut', 'En cours')->count(),
            'projets_termines'   => (clone $publishedProjects)->where('statut', 'Clôturé')->count(),
            'projets_planifies'  => (clone $publishedProjects)->where('statut', 'Concept Note')->count(),
            'projets_suspendus'  => 0,
            // Correction multidevises (§11-14) : un Financement peut être en
            // AR, USD ou EUR — plus jamais de SUM() global sans GROUP BY
            // devise. 'budget_total' est désormais { 'AR' => x, 'USD' => y, ... }.
            'budget_total'       => CurrencyAggregator::sumByDeviseQuery(Financement::query(), 'budget_approuve'),
            'total_financements' => Financement::count(),
            // Ajouts colonne 1 (mêmes conventions, calculés automatiquement)
            'nombre_beneficiaires' => (int) Project::sum('nombre_beneficiaires'),
            'nombre_regions'        => (clone $publishedProjects)->whereNotNull('region_id')->distinct('region_id')->count('region_id'),

            // ── Colonne 2 : Autres financements (nouveau) ───────────────────
            'autres_financements' => $this->autresFinancements(),

            // ── Dernière mise à jour ─────────────────────────────────────────
            'derniere_mise_a_jour' => $this->derniereMiseAJour(),
        ]);
    }

    /**
     * Statistiques "Autres financements" (hors GCF), calculées à partir des
     * modules de financement existants :
     *  - Cofinancements publics/privés = lignes Financement dont le type
     *    n'est pas "gcf".
     *  - Contributions en nature/numéraire = lignes FinancementContribution
     *    (co-financeurs, quel que soit le Financement auquel ils sont
     *    rattachés), ventilées par mode_contribution.
     *  - Partenaires techniques et financiers = même table, distinct par
     *    organisme_contributeur_id.
     *  - Budget total mobilisé hors GCF = somme des Financement non-GCF +
     *    somme des contributions (les deux sources d'argent "hors GCF"
     *    disponibles dans le modèle actuel). Les montants sont additionnés
     *    tels quels (colonne "montant"/"budget_approuve"), sans conversion
     *    de devise.
     */
    /**
     * Correction multidevises (§11-14) : chacun de ces sous-totaux peut
     * mélanger AR/USD/EUR selon les financements/contributions concernés.
     * Chaque total est désormais une ventilation { devise => montant }, et
     * 'budget_total_hors_gcf' additionne ces ventilations devise par devise
     * (CurrencyAggregator::merge), jamais entre devises différentes.
     */
    private function autresFinancements(): array
    {
        $cofinancementPublic = CurrencyAggregator::sumByDeviseQuery(
            Financement::where('type_financement', 'cofinancement_public'), 'budget_approuve'
        );
        $cofinancementPrive = CurrencyAggregator::sumByDeviseQuery(
            Financement::where('type_financement', 'cofinancement_prive'), 'budget_approuve'
        );

        $contributionsNature = CurrencyAggregator::sumByDeviseQuery(
            FinancementContribution::where('mode_contribution', 'nature'), 'montant'
        );
        $contributionsNumeraire = CurrencyAggregator::sumByDeviseQuery(
            FinancementContribution::where('mode_contribution', 'numeraire'), 'montant'
        );
        $totalContributions = CurrencyAggregator::merge($contributionsNature, $contributionsNumeraire);

        return [
            'cofinancement_public'               => $cofinancementPublic,
            'cofinancement_prive'                => $cofinancementPrive,
            'partenaires_techniques_financiers'  => $totalContributions,
            'contributions_nature'               => $contributionsNature,
            'contributions_numeraire'            => $contributionsNumeraire,
            'budget_total_hors_gcf'              => CurrencyAggregator::merge($cofinancementPublic, $cofinancementPrive, $totalContributions),
            'nombre_bailleurs_partenaires' => FinancementContribution::whereNotNull('organisme_contributeur_id')
                ->distinct('organisme_contributeur_id')->count('organisme_contributeur_id'),
        ];
    }

    /**
     * MAX(updated_at) à travers les données "principales" de la plateforme.
     * Calculé à la volée à chaque appel plutôt que stocké : reflète
     * toujours l'état réel sans avoir à modifier chaque contrôleur pour
     * mettre à jour un timestamp centralisé.
     */
    private function derniereMiseAJour(): ?string
    {
        $dates = [
            Project::max('updated_at'),
            Financement::max('updated_at'),
            Indicateur::max('updated_at'),
            Document::max('updated_at'),
            Activite::max('updated_at'),
        ];

        $latest = collect($dates)->filter()->sort()->last();

        return $latest ? \Illuminate\Support\Carbon::parse($latest)->toIso8601String() : null;
    }

    // ── PERSPECTIVES DES PROJETS (Homepage) ──────────────────────────────────
    public function perspectives(): JsonResponse
    {
        $publishedProjects = Project::where('is_published', true);

        return response()->json([
            'projets_en_preparation'          => (clone $publishedProjects)->where('statut', 'Concept Note')->count(),
            'projets_recherche_financement'   => (clone $publishedProjects)->where('statut', 'Funding Proposal')->count(),
            'projets_extension_envisagee'     => ProjectPerspective::whereHas('type', fn ($q) => $q->where('designation', 'Extension'))->distinct('project_id')->count('project_id'),
            'projets_perennisation_envisagee' => ProjectPerspective::whereHas('type', fn ($q) => $q->where('designation', 'Pérennisation'))->distinct('project_id')->count('project_id'),
            'total_perspectives'              => ProjectPerspective::count(),
            'apercu' => ProjectPerspective::with(['project:id,titre', 'type'])
                ->whereHas('project', fn ($q) => $q->where('is_published', true))
                ->orderByDesc('created_at')
                ->limit(4)
                ->get()
                ->map(fn ($p) => [
                    'id'                    => $p->id,
                    'titre'                 => $p->titre,
                    'type_id'               => $p->type_id,
                    'type'                  => $p->type?->designation,
                    'impact_futur_attendu'  => $p->impact_futur_attendu,
                    'projet'                => $p->project?->titre,
                    'project_id'            => $p->project_id,
                ]),
        ]);
    }

    // ── ADMIN GLOBAL ──────────────────────────────────────────────────────────
    public function global(): JsonResponse
    {
        return response()->json([
            'total_projets'       => Project::count(),
            'projets_actifs'      => Project::where('statut', 'En cours')->count(),
            'projets_termines'    => Project::where('statut', 'Clôturé')->count(),
            'projets_planifies'   => Project::where('statut', 'Concept Note')->count(),
            'projets_suspendus'   => Project::where('statut', 'Funding Proposal')->count(),
            // Correction multidevises (§11-14) : plus de SUM() global toutes
            // devises confondues. Chaque total est désormais { devise =>
            // montant } — remplace les anciens champs budget_usd/budget_eur/
            // budget_ar qui dupliquaient la même donnée (source unique).
            'budget_total'        => CurrencyAggregator::sumByDeviseQuery(Financement::query(), 'budget_approuve'),
            'total_financements'  => Financement::count(),
            'total_documents'     => Document::count(),
            'total_users'         => User::count(),
            'total_depenses'      => CurrencyAggregator::sumByDeviseQuery(Depense::query(), 'montant'),
            'total_engagements'   => CurrencyAggregator::sumByDeviseQuery(Engagement::query(), 'montant'),
            'total_decaissements' => CurrencyAggregator::sumByDeviseQuery(Decaissement::query(), 'montant'),
        ]);
    }

    // ── PROJETS PAR STATUT ────────────────────────────────────────────────────
    // Eloquent applique automatiquement whereNull('deleted_at') via SoftDeletes.
    public function projectsByStatus(): JsonResponse
    {
        $statuts = ['Concept Note', 'Funding Proposal', 'En cours', 'Clôturé'];

        $counts = Project::selectRaw('statut, COUNT(*) AS nb')
            ->groupBy('statut')
            ->pluck('nb', 'statut')
            ->toArray();

        $data = collect($statuts)
            ->map(fn ($s) => ['name' => $s, 'value' => (int) ($counts[$s] ?? 0)])
            ->filter(fn ($r) => $r['value'] > 0)
            ->values();

        return response()->json($data);
    }

    // ── BUDGET PAR ANNÉE ──────────────────────────────────────────────────────
    // Financement n'a PAS SoftDeletes → pas de deleted_at dans la table.
    // Eloquent::selectRaw fonctionne parfaitement pour les agrégats PostgreSQL.
    public function budgetByYear(): JsonResponse
    {
        $rows = Financement::selectRaw("
                EXTRACT(YEAR FROM date_approbation)::int                            AS annee,
                SUM(CASE WHEN devise = 'AR'  THEN budget_approuve ELSE 0 END)       AS mga,
                SUM(CASE WHEN devise = 'USD' THEN budget_approuve ELSE 0 END)       AS usd,
                SUM(CASE WHEN devise = 'EUR' THEN budget_approuve ELSE 0 END)       AS eur
            ")
            ->whereNotNull('date_approbation')
            ->groupByRaw('EXTRACT(YEAR FROM date_approbation)')
            ->orderByRaw('EXTRACT(YEAR FROM date_approbation)')
            ->get()
            ->map(fn ($r) => [
                'year' => (int)   $r->annee,
                'MGA'  => (float) $r->mga,
                'USD'  => (float) $r->usd,
                'EUR'  => (float) $r->eur,
            ]);

        return response()->json($rows);
    }

    // ── PROJETS PAR RÉGION ────────────────────────────────────────────────────
    // Eloquent applique automatiquement whereNull('deleted_at') via SoftDeletes.
    //
    // "Non défini" apparaît quand region_id est NULL sur le projet.
    // → Corriger via : Modifier le projet → Zone géographique → choisir une région.
    // → Pour masquer "Non défini" dans le graphique : supprimer le bloc if ci-dessous.
    public function projectsByRegion(): JsonResponse
    {
        $withRegion = Project::join('regions', 'projects.region_id', '=', 'regions.id')
            ->selectRaw('regions.nom AS region, COUNT(projects.id) AS nb')
            ->whereNotNull('projects.region_id')
            ->groupBy('regions.nom')
            ->orderByRaw('COUNT(projects.id) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'region' => $r->region,
                'count'  => (int) $r->nb,
            ])
            ->toArray();

        $withoutRegion = Project::whereNull('region_id')->count();

        if ($withoutRegion > 0) {
            $withRegion[] = ['region' => 'Non défini', 'count' => $withoutRegion];
        }

        // Retour : tableau direct (compatible frontend existant)
        return response()->json($withRegion);
    }

    // ── DIAGNOSTIC : projets sans région ─────────────────────────────────────
    // Endpoint séparé GET /api/v1/stats/projects-sans-region
    // Permet à l'admin de voir quels projets n'ont pas de région assignée,
    // sans modifier le format de projectsByRegion utilisé par le graphique.
    public function projectsSansRegion(): JsonResponse
    {
        $projets = Project::whereNull('region_id')
            ->select('id', 'titre', 'statut', 'created_at')
            ->orderBy('titre')
            ->get();

        return response()->json([
            'count'   => $projets->count(),
            'projets' => $projets,
            'message' => $projets->count() > 0
                ? "Ces {$projets->count()} projet(s) n'ont pas de région assignée. Modifiez-les via le formulaire → Zone géographique."
                : 'Tous les projets ont une région assignée.',
        ]);
    }

    // ── MISE À JOUR MANUELLE ──────────────────────────────────────────────────
    // DB::table() justifié ici : public_settings n'a pas de modèle Eloquent.
    public function updateManual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'total_projets'      => 'nullable|integer|min:0',
            'budget_total'       => 'nullable|numeric|min:0',
            'total_financements' => 'nullable|integer|min:0',
        ]);

        foreach ($validated as $key => $value) {
            DB::table('public_settings')
                ->updateOrInsert(['key' => $key], ['value' => $value, 'type' => 'number']);
        }

        return response()->json(['message' => 'Statistiques mises à jour.']);
    }
}

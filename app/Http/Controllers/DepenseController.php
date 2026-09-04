<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesJustificatifUploads;
use App\Models\Activite;
use App\Models\Composante;
use App\Models\Depense;
use App\Models\Project;
use App\Services\ActivityLogService;
use App\Support\CurrencyAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des dépenses du projet.
 *
 * Hiérarchie obligatoire : Projet → Composante → Activité (de la
 * composante) → Dépense, complétée par une période Année + Semestre
 * (S1/S2). Le project_id d'une dépense n'est jamais déduit de la
 * composante/activité : il reste le champ de référence explicite (comme
 * pour Result/Beneficiary), mais composante_id et activite_id doivent
 * appartenir à ce même projet — vérifié systématiquement côté serveur
 * (jamais uniquement côté frontend), voir assertHierarchy().
 */
class DepenseController extends Controller
{
    use HandlesJustificatifUploads;

    private const RELATIONS = ['project', 'financement', 'composante', 'activite'];

    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    /** Liste des dépenses (filtrable par project_id / financement_id / composante_id / activite_id / annee / semestre / categorie / statut) */
    public function index(Request $request): JsonResponse
    {
        $depenses = Depense::with(self::RELATIONS)
            ->when($request->filled('project_id'),
                fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('financement_id'),
                fn ($q) => $q->where('financement_id', $request->integer('financement_id')))
            ->when($request->filled('composante_id'),
                fn ($q) => $q->where('composante_id', $request->integer('composante_id')))
            ->when($request->filled('activite_id'),
                fn ($q) => $q->where('activite_id', $request->integer('activite_id')))
            ->when($request->filled('annee'),
                fn ($q) => $q->where('annee', $request->integer('annee')))
            ->when($request->filled('semestre'),
                fn ($q) => $q->where('semestre', $request->semestre))
            ->when($request->filled('categorie'),
                fn ($q) => $q->where('categorie', $request->categorie))
            ->when($request->filled('statut'),
                fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('search'),
                fn ($q) => $q->where(function ($qq) use ($request) {
                    $qq->where('designation', 'ilike', "%{$request->search}%")
                       ->orWhere('beneficiaire', 'ilike', "%{$request->search}%")
                       ->orWhere('reference', 'ilike', "%{$request->search}%");
                }))
            ->orderByDesc('annee')->orderByDesc('semestre')->orderByDesc('date')
            ->paginate($request->integer('per_page', 15));

        return response()->json($depenses);
    }

    private function rules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'project_id'     => $isUpdate ? 'sometimes|required|integer|exists:projects,id' : 'required|integer|exists:projects,id',
            'financement_id' => 'nullable|integer|exists:financements,id',
            // Composante et activité sont obligatoires (hiérarchie Projet →
            // Composante → Activité → Dépense) : "sometimes|required" en
            // modification permet de continuer à corriger d'anciennes
            // dépenses sans forcer leur ventilation immédiate, mais toute
            // valeur envoyée doit être cohérente (voir assertHierarchy()).
            'composante_id'  => "{$req}|integer|exists:composantes,id",
            'activite_id'    => "{$req}|integer|exists:activites,id",
            'designation'    => "{$req}|string|max:255",
            'note'           => "{$req}|string",
            'montant'        => "{$req}|numeric|gt:0",
            'devise'         => 'nullable|exists:currencies,code',
            'date'           => "{$req}|date",
            'annee'          => "{$req}|integer|digits:4|min:2000|max:2100",
            'semestre'       => "{$req}|in:S1,S2",
            'beneficiaire'   => "{$req}|string|max:255",
            'categorie'      => 'nullable|string|max:255',
            'reference'      => 'nullable|string|max:255',
        ];
    }

    private function auditRules(): array
    {
        return [
            'montant_audite'    => 'required|numeric|min:0',
            'organisme_audit'   => 'required|string|max:255',
            'date_audit'        => 'required|date',
            'observation_audit' => 'nullable|string',
            'rapport_audit'     => 'nullable|file|max:20480',
        ];
    }

    /**
     * Vérifie que la composante appartient bien au projet, et que
     * l'activité appartient bien à cette composante — jamais uniquement
     * côté frontend (§7/§18 du cahier des charges). $projectId/$composanteId
     * sont ceux effectivement retenus après fusion avec les valeurs
     * actuelles de la dépense (utile en modification partielle).
     */
    private function assertHierarchy(?int $projectId, ?int $composanteId, ?int $activiteId): void
    {
        if ($composanteId !== null) {
            $composante = Composante::find($composanteId);
            if (!$composante || (int) $composante->project_id !== (int) $projectId) {
                throw ValidationException::withMessages([
                    'composante_id' => "Cette composante n'appartient pas au projet sélectionné.",
                ]);
            }
        }

        if ($activiteId !== null) {
            $activite = Activite::find($activiteId);
            if (!$activite) {
                throw ValidationException::withMessages(['activite_id' => "Activité introuvable."]);
            }
            if ($composanteId !== null && (int) $activite->composante_id !== (int) $composanteId) {
                throw ValidationException::withMessages([
                    'activite_id' => "Cette activité n'appartient pas à la composante sélectionnée.",
                ]);
            }
            if ($activite->resolveProjectId() !== (int) $projectId) {
                throw ValidationException::withMessages([
                    'activite_id' => "Cette activité n'appartient pas au projet sélectionné.",
                ]);
            }
        }
    }

    /** Créer une dépense (étape "Budgets audités / dépensés" — sous-étape dépense) */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeEmptyStrings($request, ['financement_id', 'composante_id', 'activite_id', 'categorie', 'reference']);
        $validated = $request->validate($this->rules(false));
        unset($validated['rapport_audit']);
        $validated = $this->applyCurrencyDefaults($validated, 'montant');

        $this->assertHierarchy((int) $validated['project_id'], $validated['composante_id'] ?? null, $validated['activite_id'] ?? null);

        [$path, $name] = $this->storeJustificatif($request, 'justification', 'depenses');

        $depense = Depense::create([
            ...$validated,
            'justification_path' => $path ?? '',
            'justification_name' => $name ?? '',
            'statut'              => 'depense',
            'created_by'          => $request->user()?->id,
        ]);

        $this->logService->log(
            'create', 'depense',
            "Dépense créée : {$depense->montant} {$depense->devise} pour projet #{$depense->project_id}",
            $depense->project_id
        );

        return response()->json($depense->load(self::RELATIONS), 201);
    }

    /** Détail d'une dépense */
    public function show(int $id): JsonResponse
    {
        return response()->json(Depense::with(self::RELATIONS)->findOrFail($id));
    }

    /** Modifier une dépense (PUT sans fichier, ou POST en fallback multipart) */
    public function update(Request $request, int $id): JsonResponse
    {
        $depense = Depense::findOrFail($id);

        $this->normalizeEmptyStrings($request, ['financement_id', 'composante_id', 'activite_id', 'categorie', 'reference']);
        $validated = $request->validate($this->rules(true));
        unset($validated['rapport_audit']);

        $this->assertHierarchy(
            (int) ($validated['project_id'] ?? $depense->project_id),
            array_key_exists('composante_id', $validated) ? $validated['composante_id'] : $depense->composante_id,
            array_key_exists('activite_id', $validated) ? $validated['activite_id'] : $depense->activite_id,
        );

        [$path, $name] = $this->storeJustificatif($request, 'justification', 'depenses');
        if ($path) {
            $this->deleteJustificatifFile($depense->justification_path);
            $validated['justification_path'] = $path;
            $validated['justification_name'] = $name;
        }

        $depense->update($validated);

        $this->logService->log(
            'update', 'depense',
            "Dépense modifiée #{$depense->id} : {$depense->montant} {$depense->devise}",
            $depense->project_id
        );

        return response()->json($depense->fresh()->load(self::RELATIONS));
    }

    /**
     * Auditer une dépense (dernière sous-étape du cycle : "audité"). Ne
     * remplace pas la dépense, l'enrichit avec le résultat de l'audit.
     * PUT/POST /depenses/{id}/audit
     */
    public function audit(Request $request, int $id): JsonResponse
    {
        $depense = Depense::findOrFail($id);

        $validated = $request->validate($this->auditRules());
        unset($validated['rapport_audit']);

        [$path, $name] = $this->storeJustificatif($request, 'rapport_audit', 'depenses/audits');
        if ($path) {
            $this->deleteJustificatifFile($depense->rapport_audit_path);
            $validated['rapport_audit_path'] = $path;
            $validated['rapport_audit_name'] = $name;
        }
        $validated['statut'] = 'audite';

        $depense->update($validated);

        $this->logService->log(
            'update', 'depense',
            "Dépense auditée #{$depense->id} : {$depense->montant_audite} {$depense->devise}",
            $depense->project_id
        );

        return response()->json($depense->fresh()->load(self::RELATIONS));
    }

    /** Supprimer une dépense et ses fichiers (justification + rapport d'audit) */
    public function destroy(int $id): JsonResponse
    {
        $depense   = Depense::findOrFail($id);
        $projectId = $depense->project_id;

        $this->deleteJustificatifFile($depense->justification_path);
        $this->deleteJustificatifFile($depense->rapport_audit_path);

        $depense->delete();

        $this->logService->log('delete', 'depense', "Dépense supprimée #{$id}", $projectId);

        return response()->json(['message' => 'Dépense supprimée.']);
    }

    /** Télécharger la pièce justificative de la dépense */
    public function downloadJustification(int $id)
    {
        $depense = Depense::findOrFail($id);
        return $this->downloadJustificatifResponse($depense->justification_path, $depense->justification_name);
    }

    /** Télécharger le rapport d'audit */
    public function downloadRapportAudit(int $id)
    {
        $depense = Depense::findOrFail($id);
        return $this->downloadJustificatifResponse($depense->rapport_audit_path, $depense->rapport_audit_name);
    }

    /**
     * Vue consolidée des dépenses du projet, respectant strictement la
     * hiérarchie Projet → Composante → Activité, avec totaux par activité,
     * par composante, par projet, par semestre/année, et comparaison au
     * budget prévu (Composante::budget / Activite::budget déjà existants).
     * Lecture seule.
     *
     * Correction multidevises (cahier des charges §9-10) : les dépenses
     * d'une même activité/composante/projet peuvent être dans plusieurs
     * devises (AR, USD, EUR) — tous les totaux sont donc désormais des
     * tableaux { devise => montant } (`totaux_depense`), jamais un montant
     * unique mélangeant les devises. Le solde et le taux d'exécution ne
     * sont calculés QUE pour la devise du budget concerné (Composante::devise
     * / Activite::devise, ajoutées par la migration
     * 2026_09_02_080000_add_devise_to_composantes_and_activites), car
     * comparer un budget dans une devise à une dépense dans une autre
     * n'a pas de sens sans mécanisme de conversion (§10). Les dépenses
     * faites dans une autre devise que celle du budget restent visibles
     * dans `totaux_depense`, mais n'entrent dans aucun solde/taux.
     *
     * GET /projects/{project}/depenses-summary
     */
    public function summaryForProject(int $projectId): JsonResponse
    {
        Project::findOrFail($projectId);

        $depenses = Depense::where('project_id', $projectId)->get();

        // Compare un budget mono-devise à la ventilation de dépenses
        // { devise => montant } : ne renvoie solde/taux que pour la devise
        // du budget lui-même, jamais en mélangeant avec les autres devises
        // présentes dans $totauxDepense.
        $compareToBudget = function (?float $budget, ?string $budgetDevise, array $totauxDepense): array {
            if ($budget === null || $budgetDevise === null) {
                return ['solde' => null, 'taux_execution' => null];
            }
            $depenseDansLaDevise = $totauxDepense[$budgetDevise] ?? 0.0;

            return [
                'solde'          => round($budget - $depenseDansLaDevise, 2),
                'taux_execution' => $budget > 0 ? round(($depenseDansLaDevise / $budget) * 100, 1) : null,
            ];
        };

        // NB: la colonne "ordre" existe sur "composantes" mais pas sur
        // "activites" (cf. migration create_activites_table) — trier les
        // activités par "created_at" pour éviter une erreur SQL.
        $composantes = Composante::where('project_id', $projectId)
            ->with(['activites' => fn ($q) => $q->orderBy('created_at')])
            ->orderBy('ordre')
            ->get();

        $composantesOut = $composantes->map(function (Composante $composante) use ($depenses, $compareToBudget) {
            $activitesOut = $composante->activites->map(function (Activite $activite) use ($depenses, $compareToBudget) {
                $activiteDepenses = $depenses->where('activite_id', $activite->id);
                $totaux = CurrencyAggregator::sumByDevise($activiteDepenses, 'montant');
                $budget = $activite->budget !== null ? (float) $activite->budget : null;

                return [
                    'id'            => $activite->id,
                    'nom'           => $activite->nom,
                    'budget'        => $budget,
                    'devise'        => $activite->devise,
                    'totaux_depense' => $totaux,
                    ...$compareToBudget($budget, $activite->devise, $totaux),
                    'par_periode'   => $activiteDepenses
                        ->groupBy(fn ($d) => "{$d->annee}-{$d->semestre}")
                        ->map(fn ($group) => [
                            'annee'    => $group->first()->annee,
                            'semestre' => $group->first()->semestre,
                            'totaux'   => CurrencyAggregator::sumByDevise($group, 'montant'),
                        ])
                        ->values(),
                ];
            })->values();

            $composanteDepenses = $depenses->where('composante_id', $composante->id);
            $totaux = CurrencyAggregator::sumByDevise($composanteDepenses, 'montant');
            $budget = $composante->budget !== null ? (float) $composante->budget : null;

            return [
                'id'             => $composante->id,
                'nom'            => $composante->nom,
                'budget'         => $budget,
                'devise'         => $composante->devise,
                'totaux_depense' => $totaux,
                ...$compareToBudget($budget, $composante->devise, $totaux),
                'activites'      => $activitesOut,
            ];
        })->values();

        // Dépenses non rattachées à une composante/activité (données
        // historiques créées avant que la hiérarchie ne soit obligatoire —
        // conservées telles quelles, jamais supprimées ni modifiées de force).
        $nonVentilees = $depenses->filter(fn ($d) => !$d->composante_id || !$d->activite_id);

        $totauxProjet = CurrencyAggregator::sumByDevise($depenses, 'montant');
        // Budget du projet ventilé par devise : chaque composante peut avoir
        // sa propre devise de budget, donc on regroupe plutôt que de sommer
        // un seul nombre (cf. §11 — jamais de total global multidevise).
        $budgetProjetParDevise = CurrencyAggregator::sumByDevise($composantes->whereNotNull('budget'), 'budget');

        $soldesProjet = collect($budgetProjetParDevise)
            ->mapWithKeys(fn ($budget, $devise) => [
                $devise => round($budget - ($totauxProjet[$devise] ?? 0.0), 2),
            ])->all();

        $tauxExecutionProjet = collect($budgetProjetParDevise)
            ->mapWithKeys(fn ($budget, $devise) => [
                $devise => $budget > 0 ? round((($totauxProjet[$devise] ?? 0.0) / $budget) * 100, 1) : null,
            ])->all();

        $parPeriode = $depenses
            ->groupBy(fn ($d) => "{$d->annee}-{$d->semestre}")
            ->map(fn ($group) => [
                'annee'    => $group->first()->annee,
                'semestre' => $group->first()->semestre,
                'totaux'   => CurrencyAggregator::sumByDevise($group, 'montant'),
            ])
            ->sortBy([['annee', 'asc'], ['semestre', 'asc']])
            ->values();

        $parAnnee = $depenses
            ->groupBy('annee')
            ->map(fn ($group, $annee) => [
                'annee'  => (int) $annee,
                'totaux' => CurrencyAggregator::sumByDevise($group, 'montant'),
            ])
            ->sortBy('annee')
            ->values();

        return response()->json([
            'composantes'   => $composantesOut,
            'non_ventilees' => [
                'count'          => $nonVentilees->count(),
                'totaux_depense' => CurrencyAggregator::sumByDevise($nonVentilees, 'montant'),
            ],
            'project' => [
                'budget'         => $budgetProjetParDevise,
                'totaux_depense' => $totauxProjet,
                'solde'          => $soldesProjet,
                'taux_execution' => $tauxExecutionProjet,
            ],
            'par_periode' => $parPeriode,
            'par_annee'   => $parAnnee,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinancementResource;
use App\Models\Financement;
use App\Models\FinancementContribution;
use App\Services\ActivityLogService;
use App\Support\CurrencyAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class FinancementController extends Controller
{
    private const CONTRIB_RELATIONS = ['project', 'categorieContribution', 'contributions.organismeContributeur', 'contributions.categorieContribution'];

    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $financements = Financement::with(self::CONTRIB_RELATIONS)
            ->when($request->filled('project_id'),         fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('source_financement'), fn ($q) => $q->where('source_financement', 'ilike', "%{$request->source_financement}%"))
            ->orderByDesc('date_approbation')
            ->paginate($request->integer('per_page', 15));

        return FinancementResource::collection($financements);
    }

    /**
     * Retourne les totaux agrégés de tous les financements (sans pagination).
     * NB : n'inclut pour l'instant que le montant de la "Source du
     * financement" principale (colonne budget_approuve de financements),
     * pas encore les lignes de financement_contributions — à étendre si
     * besoin d'un total consolidé source + co-financeurs.
     * GET /api/financements/totaux
     */
    public function totaux(Request $request): JsonResponse
    {
        $query = Financement::query()
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')));

        $total_count = (clone $query)->count();

        // Regroupement par devise via le helper centralisé (comportement
        // inchangé pour le frontend : clés USD/EUR/AR toujours présentes,
        // à 0 si aucun financement dans cette devise).
        $totaux = CurrencyAggregator::sumByDeviseQuery(clone $query, 'budget_approuve');

        return response()->json([
            'total_count' => $total_count,
            'totaux' => [
                'USD' => $totaux['USD'] ?? 0.0,
                'EUR' => $totaux['EUR'] ?? 0.0,
                'AR'  => $totaux['AR']  ?? 0.0,
            ],
        ]);
    }

    public function byProject(int $projectId): JsonResponse
    {
        $financements = Financement::with(self::CONTRIB_RELATIONS)
            ->where('project_id', $projectId)
            ->orderByDesc('date_approbation')
            ->get();

        $totaux = CurrencyAggregator::sumByDevise($financements, 'budget_approuve');

        return response()->json([
            'data'   => FinancementResource::collection($financements),
            'totaux' => [
                'AR'  => $totaux['AR']  ?? 0.0,
                'USD' => $totaux['USD'] ?? 0.0,
                'EUR' => $totaux['EUR'] ?? 0.0,
            ],
        ]);
    }

    /**
     * Règle métier GCF appliquée AVANT validation : un financement "GCF" est
     * toujours en numéraire pour sa "Source du financement" principale,
     * quoi qu'envoie le frontend — on écrase donc mode_contribution ici,
     * côté serveur. Cette règle ne s'applique qu'à la source principale :
     * chaque ligne de financement_contributions garde son propre mode de
     * contribution, choisi librement par l'utilisateur (co-financeurs
     * indépendants de la nature du financement principal).
     */
    private function enforceGcfCashRule(Request $request): void
    {
        if ($request->input('type_financement') === 'gcf') {
            $request->merge(['mode_contribution' => 'numeraire']);
        }
    }

    /**
     * Règle métier : une contribution "en nature" est toujours valorisée en
     * Ariary (AR/MGA) — il n'y a pas de transfert de devise étrangère pour
     * une contribution en nature, seulement une estimation en monnaie
     * locale. On force donc devise=AR côté serveur dès que le mode de
     * contribution (source principale OU une ligne de co-financement) est
     * "nature", quoi qu'envoie le frontend.
     */
    private function enforceNatureCurrencyRule(Request $request): void
    {
        if ($request->input('mode_contribution') === 'nature') {
            $request->merge(['devise' => 'AR']);
        }

        $contributions = $request->input('contributions');
        if (is_array($contributions)) {
            $request->merge([
                'contributions' => array_map(function ($c) {
                    if (is_array($c) && ($c['mode_contribution'] ?? null) === 'nature') {
                        $c['devise'] = 'AR';
                    }
                    return $c;
                }, $contributions),
            ]);
        }
    }

    private function rules(bool $isUpdate, string $modeContribution): array
    {
        $isNature = $modeContribution === 'nature';
        // IMPORTANT : quand les règles sont données sous forme de tableau
        // (et non de chaîne "a|b|c"), chaque règle doit être un élément
        // séparé — 'sometimes|required' comme UN SEUL élément n'est pas
        // reconnu par Laravel (il cherche une règle nommée littéralement
        // "sometimes|required", qui n'existe pas, et lève une
        // BadMethodCallException). D'où ce tableau plutôt qu'une chaîne.
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'project_id'         => [...($isUpdate ? ['sometimes'] : ['required']), 'integer', 'exists:projects,id'],
            'type_financement'   => [...$req, 'in:gcf,cofinancement_public,cofinancement_prive'],
            'statut'             => [...$req, 'in:subvention,pret,credit,don'],
            'mode_contribution'  => [...$req, 'in:numeraire,nature'],

            'source_financement' => [...$req, 'string', 'max:255'],

            'budget_approuve'    => [...$req, 'numeric', 'min:0'],
            'devise'             => [...$req, 'exists:currencies,code'],
            'date_approbation'   => [...$req, 'date_format:Y-m-d'],

            'description'        => [...($isNature ? $req : ['nullable']), 'string'],

            'categorie_contribution_id' => [...($isNature ? $req : ['prohibited']), 'integer', 'exists:contribution_categories,id'],

            // ── Organismes contributeurs additionnels (co-financeurs) ──
            'contributions'                             => ['nullable', 'array'],
            'contributions.*.id'                         => ['nullable', 'integer', 'exists:financement_contributions,id'],
            'contributions.*.organisme_contributeur_id'  => ['required', 'integer', 'exists:organismes_contributeurs,id'],
            'contributions.*.mode_contribution'          => ['required', 'in:numeraire,nature'],
            'contributions.*.montant'                    => ['required', 'numeric', 'min:0'],
            'contributions.*.devise'                     => ['required', 'exists:currencies,code'],
            'contributions.*.date_contribution'          => ['required', 'date_format:Y-m-d'],
            'contributions.*.categorie_contribution_id'  => ['nullable', 'integer', 'exists:contribution_categories,id'],
            'contributions.*.description'                => ['nullable', 'string'],
        ];
    }

    private function messages(): array
    {
        return [
            'project_id.required'         => 'Le projet est obligatoire.',
            'project_id.exists'           => 'Ce projet n\'existe pas.',
            'type_financement.required'   => 'Le type de financement est obligatoire.',
            'type_financement.in'         => 'Type de financement invalide.',
            'mode_contribution.required'  => 'Le mode de contribution est obligatoire.',
            'mode_contribution.in'        => 'Mode de contribution invalide.',
            'source_financement.required' => 'La source de financement est obligatoire.',
            'budget_approuve.required'    => 'Le budget approuvé / la valeur estimée est obligatoire.',
            'budget_approuve.numeric'     => 'Ce montant doit être un nombre.',
            'budget_approuve.min'         => 'Ce montant doit être positif.',
            'devise.required'             => 'La devise est obligatoire.',
            'devise.in'                   => 'Devise invalide. Valeurs acceptées : AR, USD, EUR.',
            'date_approbation.required'   => "La date d'approbation / de mise à disposition est obligatoire.",
            'date_approbation.date_format'=> 'La date doit être au format AAAA-MM-JJ.',
            'description.required'        => 'La description de la contribution est obligatoire pour une contribution en nature.',
            'categorie_contribution_id.required' => 'La catégorie de la contribution est obligatoire pour une contribution en nature.',
            'categorie_contribution_id.prohibited' => 'La catégorie de contribution ne s\'applique qu\'aux contributions en nature.',
            'contributions.*.organisme_contributeur_id.required' => "L'organisme contributeur est obligatoire pour chaque ligne de co-financement.",
            'contributions.*.montant.required' => 'Le montant est obligatoire pour chaque organisme contributeur.',
            'contributions.*.date_contribution.required' => 'La date est obligatoire pour chaque organisme contributeur.',
        ];
    }

    /**
     * Vérifie, pour chaque ligne de contribution, que les champs "en nature"
     * sont fournis quand le mode de CETTE ligne est nature, et absents sinon
     * — indépendamment du mode de la Source du financement principale.
     */
    private function validateContributionsNatureRules(array $contributions): void
    {
        foreach ($contributions as $i => $c) {
            $isNature = ($c['mode_contribution'] ?? null) === 'nature';
            if ($isNature) {
                if (empty($c['categorie_contribution_id'])) {
                    throw ValidationException::withMessages([
                        "contributions.$i.categorie_contribution_id" => 'La catégorie de la contribution est obligatoire pour une contribution en nature.',
                    ]);
                }
                if (empty($c['description'])) {
                    throw ValidationException::withMessages([
                        "contributions.$i.description" => 'La description de la contribution est obligatoire pour une contribution en nature.',
                    ]);
                }
            } elseif (!empty($c['categorie_contribution_id'])) {
                throw ValidationException::withMessages([
                    "contributions.$i.mode_contribution" => "La catégorie ne s'applique qu'aux contributions en nature.",
                ]);
            }
        }
    }

    private function contributionPayload(array $c): array
    {
        $isNature = $c['mode_contribution'] === 'nature';
        return [
            'organisme_contributeur_id' => $c['organisme_contributeur_id'],
            'mode_contribution'         => $c['mode_contribution'],
            'montant'                   => $c['montant'],
            'devise'                    => $c['devise'],
            'date_contribution'         => $c['date_contribution'],
            'categorie_contribution_id' => $isNature ? ($c['categorie_contribution_id'] ?? null) : null,
            'description'               => $isNature ? ($c['description'] ?? null) : null,
        ];
    }

    public function store(Request $request): FinancementResource
    {
        $this->enforceGcfCashRule($request);
        $this->enforceNatureCurrencyRule($request);
        $modeContribution = $request->input('mode_contribution', 'numeraire');

        $validated = $request->validate($this->rules(false, $modeContribution), $this->messages());
        $contributions = $validated['contributions'] ?? [];
        unset($validated['contributions']);
        $this->validateContributionsNatureRules($contributions);

        $financement = Financement::create($validated);

        foreach ($contributions as $c) {
            $financement->contributions()->create($this->contributionPayload($c));
        }

        $this->logService->log(
            'create', 'financement',
            "Financement ajouté : {$financement->source_financement} — {$financement->budget_approuve} {$financement->devise}" .
                ($contributions ? ' (+' . count($contributions) . ' co-financeur(s))' : ''),
            $financement->project_id
        );

        return new FinancementResource($financement->load(self::CONTRIB_RELATIONS));
    }

    public function show(int $id): FinancementResource
    {
        return new FinancementResource(
            Financement::with(array_merge(self::CONTRIB_RELATIONS, ['documents']))->findOrFail($id)
        );
    }

    public function update(Request $request, int $id): FinancementResource
    {
        $financement = Financement::findOrFail($id);

        $this->enforceGcfCashRule($request);
        $this->enforceNatureCurrencyRule($request);
        $modeContribution = $request->input('mode_contribution', $financement->mode_contribution);

        $validated = $request->validate($this->rules(true, $modeContribution), $this->messages());
        $contributions = $validated['contributions'] ?? null;
        unset($validated['contributions']);

        if ($contributions !== null) {
            $this->validateContributionsNatureRules($contributions);
        }

        if ($modeContribution !== 'nature') {
            $validated['categorie_contribution_id'] = null;
        }

        $financement->update($validated);

        // Synchronisation des lignes de contribution : on ne touche à rien
        // si le champ "contributions" n'a pas été envoyé dans la requête
        // (permet des mises à jour partielles depuis d'autres écrans sans
        // effacer les co-financeurs existants).
        if ($contributions !== null) {
            $existingIds = $financement->contributions()->pluck('id')->all();
            $incomingIds = array_filter(array_column($contributions, 'id'));
            $toDelete    = array_diff($existingIds, $incomingIds);

            if ($toDelete) {
                FinancementContribution::whereIn('id', $toDelete)->delete();
            }

            foreach ($contributions as $c) {
                $payload = $this->contributionPayload($c);
                if (!empty($c['id'])) {
                    $financement->contributions()->where('id', $c['id'])->update($payload);
                } else {
                    $financement->contributions()->create($payload);
                }
            }
        }

        $this->logService->log(
            'update', 'financement',
            "Financement modifié : {$financement->source_financement}",
            $financement->project_id
        );

        return new FinancementResource($financement->load(self::CONTRIB_RELATIONS));
    }

    public function destroy(int $id): JsonResponse
    {
        $financement = Financement::findOrFail($id);

        $this->logService->log(
            'delete', 'financement',
            "Financement supprimé : {$financement->source_financement} — {$financement->budget_approuve} {$financement->devise}",
            $financement->project_id
        );

        $financement->delete();

        return response()->json(['message' => 'Financement supprimé.']);
    }
}

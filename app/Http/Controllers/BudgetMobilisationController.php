<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesJustificatifUploads;
use App\Models\Financement;
use App\Models\FinancementContribution;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Étape 2 du cycle budgétaire : "Budgets mobilisés" (Mobilised Finance).
 *
 * Repose sur le modèle FinancementContribution déjà utilisé par le
 * formulaire Financement pour les co-financeurs — cette table EST la liste
 * des montants mobilisés grâce à des financements publics/privés,
 * cofinancements ou effets de levier. Ce contrôleur expose un point d'entrée
 * dédié pour le suivi de cycle (avec type de mobilisation, commentaire et
 * justificatif), indépendant du formulaire d'édition d'un Financement — les
 * deux opèrent sur la même table sans se marcher dessus : l'un gère la
 * composition structurelle du financement, l'autre le suivi chronologique.
 */
class BudgetMobilisationController extends Controller
{
    use HandlesJustificatifUploads;

    private const RELATIONS = ['organismeContributeur', 'categorieContribution', 'composante', 'activite'];

    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // GET /financements/{financement}/mobilisations
    public function index(int $financementId): JsonResponse
    {
        Financement::findOrFail($financementId);

        $items = FinancementContribution::with(self::RELATIONS)
            ->where('financement_id', $financementId)
            ->orderByDesc('date_contribution')
            ->get();

        return response()->json($items);
    }

    private function rules(bool $isUpdate, string $modeContribution): array
    {
        $req      = $isUpdate ? 'sometimes|required' : 'required';
        $isNature = $modeContribution === 'nature';

        return [
            'organisme_contributeur_id' => "{$req}|integer|exists:organismes_contributeurs,id",
            'mode_contribution'         => "{$req}|in:numeraire,nature",
            'type_mobilisation'         => 'nullable|in:public,prive,cofinancement,effet_levier',
            'montant'                   => "{$req}|numeric|min:0",
            'devise'                    => "{$req}|exists:currencies,code",
            'date_contribution'         => "{$req}|date",
            'categorie_contribution_id' => [$isNature ? 'required' : 'prohibited', 'integer', 'exists:contribution_categories,id'],
            'description'               => [$isNature ? 'required' : 'nullable', 'string'],
            'commentaire'               => 'nullable|string',
            'composante_id'             => 'nullable|integer|exists:composantes,id',
            'activite_id'               => 'nullable|integer|exists:activites,id',
            'justificatif'              => 'nullable|file|max:20480',
        ];
    }

    private function messages(): array
    {
        return [
            'categorie_contribution_id.required'   => 'La catégorie de la contribution est obligatoire pour une contribution en nature.',
            'categorie_contribution_id.prohibited' => "La catégorie ne s'applique qu'aux contributions en nature.",
            'description.required'                 => 'La description est obligatoire pour une contribution en nature.',
        ];
    }

    // POST /financements/{financement}/mobilisations (multipart)
    public function store(Request $request, int $financementId): JsonResponse
    {
        $financement = Financement::findOrFail($financementId);

        $this->normalizeEmptyStrings($request, [
            'categorie_contribution_id', 'composante_id', 'activite_id', 'description', 'commentaire', 'type_mobilisation',
        ]);
        $modeContribution = $request->input('mode_contribution', 'numeraire');
        $validated = $request->validate($this->rules(false, $modeContribution), $this->messages());
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/mobilisations');

        $mobilisation = FinancementContribution::create([
            ...$validated,
            'financement_id'     => $financement->id,
            'justificatif_path'  => $path,
            'justificatif_name'  => $name,
        ]);

        $this->logService->log(
            'create', 'budget_mobilisation',
            "Budget mobilisé ajouté : {$mobilisation->montant} {$mobilisation->devise}",
            $financement->project_id
        );

        return response()->json($mobilisation->load(self::RELATIONS), 201);
    }

    // GET /mobilisations/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(FinancementContribution::with(self::RELATIONS)->findOrFail($id));
    }

    // PUT /mobilisations/{id}  ou  POST /mobilisations/{id} (fallback multipart)
    public function update(Request $request, int $id): JsonResponse
    {
        $mobilisation = FinancementContribution::findOrFail($id);

        $this->normalizeEmptyStrings($request, [
            'categorie_contribution_id', 'composante_id', 'activite_id', 'description', 'commentaire', 'type_mobilisation',
        ]);
        $modeContribution = $request->input('mode_contribution', $mobilisation->mode_contribution);
        $validated = $request->validate($this->rules(true, $modeContribution), $this->messages());
        unset($validated['justificatif']);

        if ($modeContribution !== 'nature') {
            $validated['categorie_contribution_id'] = null;
        }

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/mobilisations');
        if ($path) {
            $this->deleteJustificatifFile($mobilisation->justificatif_path);
            $validated['justificatif_path'] = $path;
            $validated['justificatif_name'] = $name;
        }

        $mobilisation->update($validated);

        $this->logService->log(
            'update', 'budget_mobilisation',
            "Budget mobilisé modifié #{$mobilisation->id}",
            $mobilisation->financement->project_id
        );

        return response()->json($mobilisation->fresh()->load(self::RELATIONS));
    }

    // DELETE /mobilisations/{id}
    public function destroy(int $id): JsonResponse
    {
        $mobilisation = FinancementContribution::findOrFail($id);
        $projectId    = $mobilisation->financement->project_id;

        $this->deleteJustificatifFile($mobilisation->justificatif_path);
        $mobilisation->delete();

        $this->logService->log('delete', 'budget_mobilisation', "Budget mobilisé supprimé #{$id}", $projectId);

        return response()->json(['message' => 'Budget mobilisé supprimé.']);
    }

    // GET /mobilisations/{id}/download
    public function download(int $id)
    {
        $mobilisation = FinancementContribution::findOrFail($id);

        return $this->downloadJustificatifResponse($mobilisation->justificatif_path, $mobilisation->justificatif_name);
    }
}

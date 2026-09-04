<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesJustificatifUploads;
use App\Models\BudgetApprobation;
use App\Models\Financement;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Étape 4 du cycle budgétaire : "Budgets approuvés" (Approved).
 *
 * Historique des décisions d'approbation d'un Financement, en complément du
 * couple (budget_approuve, date_approbation) porté par Financement lui-même
 * (qui reste la valeur "de référence" affichée ailleurs dans l'application).
 */
class BudgetApprobationController extends Controller
{
    use HandlesJustificatifUploads;

    private const RELATIONS = ['organisme', 'composante', 'activite'];

    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // GET /financements/{financement}/approbations
    public function index(int $financementId): JsonResponse
    {
        Financement::findOrFail($financementId);

        $items = BudgetApprobation::with(self::RELATIONS)
            ->where('financement_id', $financementId)
            ->orderByDesc('date_approbation')
            ->get();

        return response()->json($items);
    }

    private function rules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'date_approbation' => "{$req}|date",
            'organisme_id'     => 'nullable|integer|exists:organismes_contributeurs,id',
            'montant_approuve' => "{$req}|numeric|min:0",
            'devise'           => "{$req}|exists:currencies,code",
            'reference'        => 'nullable|string|max:255',
            'decision'         => 'nullable|string',
            'composante_id'    => 'nullable|integer|exists:composantes,id',
            'activite_id'      => 'nullable|integer|exists:activites,id',
            'justificatif'     => 'nullable|file|max:20480',
        ];
    }

    // POST /financements/{financement}/approbations (multipart)
    public function store(Request $request, int $financementId): JsonResponse
    {
        $financement = Financement::findOrFail($financementId);

        $this->normalizeEmptyStrings($request, ['organisme_id', 'composante_id', 'activite_id', 'reference', 'decision']);
        $validated = $request->validate($this->rules(false));
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/approbations');

        $approbation = BudgetApprobation::create([
            ...$validated,
            'financement_id'    => $financement->id,
            'justificatif_path' => $path,
            'justificatif_name' => $name,
            'created_by'        => $request->user()?->id,
        ]);

        $this->logService->log(
            'create', 'budget_approbation',
            "Budget approuvé ajouté : {$approbation->montant_approuve} {$approbation->devise}",
            $financement->project_id
        );

        return response()->json($approbation->load(self::RELATIONS), 201);
    }

    // GET /approbations/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(BudgetApprobation::with(self::RELATIONS)->findOrFail($id));
    }

    // PUT /approbations/{id}  ou  POST /approbations/{id} (fallback multipart)
    public function update(Request $request, int $id): JsonResponse
    {
        $approbation = BudgetApprobation::findOrFail($id);

        $this->normalizeEmptyStrings($request, ['organisme_id', 'composante_id', 'activite_id', 'reference', 'decision']);
        $validated = $request->validate($this->rules(true));
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/approbations');
        if ($path) {
            $this->deleteJustificatifFile($approbation->justificatif_path);
            $validated['justificatif_path'] = $path;
            $validated['justificatif_name'] = $name;
        }

        $approbation->update($validated);

        $this->logService->log(
            'update', 'budget_approbation',
            "Budget approuvé modifié #{$approbation->id}",
            $approbation->financement->project_id
        );

        return response()->json($approbation->fresh()->load(self::RELATIONS));
    }

    // DELETE /approbations/{id}
    public function destroy(int $id): JsonResponse
    {
        $approbation = BudgetApprobation::findOrFail($id);
        $projectId   = $approbation->financement->project_id;

        $this->deleteJustificatifFile($approbation->justificatif_path);
        $approbation->delete();

        $this->logService->log('delete', 'budget_approbation', "Budget approuvé supprimé #{$id}", $projectId);

        return response()->json(['message' => 'Budget approuvé supprimé.']);
    }

    // GET /approbations/{id}/download
    public function download(int $id)
    {
        $approbation = BudgetApprobation::findOrFail($id);

        return $this->downloadJustificatifResponse($approbation->justificatif_path, $approbation->justificatif_name);
    }
}

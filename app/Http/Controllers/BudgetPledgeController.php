<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesJustificatifUploads;
use App\Models\BudgetPledge;
use App\Models\Financement;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Étape 1 du cycle budgétaire : "Budgets annoncés / promis" (Pledges).
 */
class BudgetPledgeController extends Controller
{
    use HandlesJustificatifUploads;

    private const RELATIONS = ['bailleur', 'composante', 'activite'];

    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // GET /financements/{financement}/pledges
    public function index(int $financementId): JsonResponse
    {
        Financement::findOrFail($financementId);

        $items = BudgetPledge::with(self::RELATIONS)
            ->where('financement_id', $financementId)
            ->orderByDesc('date_annonce')
            ->get();

        return response()->json($items);
    }

    private function rules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'date_annonce'  => "{$req}|date",
            'bailleur_id'   => 'nullable|integer|exists:organismes_contributeurs,id',
            'montant'       => "{$req}|numeric|min:0",
            'devise'        => "{$req}|exists:currencies,code",
            'description'   => 'nullable|string',
            'source'        => 'nullable|string|max:255',
            'composante_id' => 'nullable|integer|exists:composantes,id',
            'activite_id'   => 'nullable|integer|exists:activites,id',
            'justificatif'  => 'nullable|file|max:20480',
        ];
    }

    // POST /financements/{financement}/pledges (multipart)
    public function store(Request $request, int $financementId): JsonResponse
    {
        $financement = Financement::findOrFail($financementId);

        $this->normalizeEmptyStrings($request, ['bailleur_id', 'composante_id', 'activite_id', 'description', 'source']);
        $validated = $request->validate($this->rules(false));
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/pledges');

        $pledge = BudgetPledge::create([
            ...$validated,
            'financement_id'     => $financement->id,
            'justificatif_path'  => $path,
            'justificatif_name'  => $name,
            'created_by'         => $request->user()?->id,
        ]);

        $this->logService->log(
            'create', 'budget_pledge',
            "Budget annoncé ajouté : {$pledge->montant} {$pledge->devise}",
            $financement->project_id
        );

        return response()->json($pledge->load(self::RELATIONS), 201);
    }

    // GET /pledges/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(BudgetPledge::with(self::RELATIONS)->findOrFail($id));
    }

    // PUT /pledges/{id}  ou  POST /pledges/{id} (fallback multipart pour l'upload)
    public function update(Request $request, int $id): JsonResponse
    {
        $pledge = BudgetPledge::findOrFail($id);

        $this->normalizeEmptyStrings($request, ['bailleur_id', 'composante_id', 'activite_id', 'description', 'source']);
        $validated = $request->validate($this->rules(true));
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/pledges');
        if ($path) {
            $this->deleteJustificatifFile($pledge->justificatif_path);
            $validated['justificatif_path'] = $path;
            $validated['justificatif_name'] = $name;
        }

        $pledge->update($validated);

        $this->logService->log(
            'update', 'budget_pledge',
            "Budget annoncé modifié #{$pledge->id}",
            $pledge->financement->project_id
        );

        return response()->json($pledge->fresh()->load(self::RELATIONS));
    }

    // DELETE /pledges/{id}
    public function destroy(int $id): JsonResponse
    {
        $pledge    = BudgetPledge::findOrFail($id);
        $projectId = $pledge->financement->project_id;

        $this->deleteJustificatifFile($pledge->justificatif_path);
        $pledge->delete();

        $this->logService->log('delete', 'budget_pledge', "Budget annoncé supprimé #{$id}", $projectId);

        return response()->json(['message' => 'Budget annoncé supprimé.']);
    }

    // GET /pledges/{id}/download
    public function download(int $id)
    {
        $pledge = BudgetPledge::findOrFail($id);

        return $this->downloadJustificatifResponse($pledge->justificatif_path, $pledge->justificatif_name);
    }
}

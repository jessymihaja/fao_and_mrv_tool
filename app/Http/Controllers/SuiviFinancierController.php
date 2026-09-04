<?php
// app/Http/Controllers/SuiviFinancierController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesJustificatifUploads;
use App\Models\Decaissement;
use App\Models\DecaissementPlan;
use App\Models\Depense;
use App\Models\Engagement;
use App\Models\Financement;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuiviFinancierController extends Controller
{
    use HandlesJustificatifUploads;

    private const ENGAGEMENT_RELATIONS   = ['bailleur', 'composante', 'activite'];
    private const PLAN_RELATIONS         = ['composante', 'activite'];
    private const DECAISSEMENT_RELATIONS = ['composante', 'activite'];

    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // ── ENGAGEMENTS (étape "Budgets engagés") ───────────────────────────────

    public function engagements(int $financementId): JsonResponse
    {
        Financement::findOrFail($financementId);
        $items = Engagement::with(self::ENGAGEMENT_RELATIONS)
            ->where('financement_id', $financementId)
            ->orderByDesc('date')->get();
        return response()->json($items);
    }

    private function engagementRules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'date'             => "{$req}|date",
            'montant'          => "{$req}|numeric|min:0",
            'devise'           => 'nullable|exists:currencies,code',
            'reference_accord' => 'nullable|string|max:255',
            'bailleur_id'      => 'nullable|integer|exists:organismes_contributeurs,id',
            'description'      => 'nullable|string',
            'composante_id'    => 'nullable|integer|exists:composantes,id',
            'activite_id'      => 'nullable|integer|exists:activites,id',
            'justificatif'     => 'nullable|file|max:20480',
        ];
    }

    public function storeEngagement(Request $request, int $financementId): JsonResponse
    {
        $financement = Financement::findOrFail($financementId);

        $this->normalizeEmptyStrings($request, ['reference_accord', 'bailleur_id', 'description', 'composante_id', 'activite_id']);
        $validated = $request->validate($this->engagementRules(false));
        unset($validated['justificatif']);
        $validated = $this->applyCurrencyDefaults($validated, 'montant');

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/engagements');

        $engagement = Engagement::create([
            ...$validated,
            'financement_id'    => $financementId,
            'justificatif_path' => $path,
            'justificatif_name' => $name,
        ]);

        $this->logService->log('create', 'engagement', "Accord ajouté : {$engagement->montant} {$engagement->devise}", $financement->project_id);

        return response()->json($engagement->load(self::ENGAGEMENT_RELATIONS), 201);
    }

    public function updateEngagement(Request $request, int $id): JsonResponse
    {
        $engagement = Engagement::findOrFail($id);

        $this->normalizeEmptyStrings($request, ['reference_accord', 'bailleur_id', 'description', 'composante_id', 'activite_id']);
        $validated = $request->validate($this->engagementRules(true));
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/engagements');
        if ($path) {
            $this->deleteJustificatifFile($engagement->justificatif_path);
            $validated['justificatif_path'] = $path;
            $validated['justificatif_name'] = $name;
        }

        $engagement->update($validated);

        $this->logService->log('update', 'engagement', "Accord modifié #{$engagement->id}", $engagement->financement->project_id);

        return response()->json($engagement->fresh()->load(self::ENGAGEMENT_RELATIONS));
    }

    public function destroyEngagement(int $id): JsonResponse
    {
        $engagement = Engagement::findOrFail($id);
        $projectId  = $engagement->financement->project_id;

        $this->deleteJustificatifFile($engagement->justificatif_path);
        $engagement->delete();

        $this->logService->log('delete', 'engagement', "Accord supprimé #{$id}", $projectId);

        return response()->json(['message' => 'Supprimé.']);
    }

    public function downloadEngagement(int $id)
    {
        $engagement = Engagement::findOrFail($id);
        return $this->downloadJustificatifResponse($engagement->justificatif_path, $engagement->justificatif_name);
    }

    // ── PLANS DE DÉCAISSEMENT (étape "Budgets programmés / planifiés") ─────

    public function decaissementPlans(int $financementId): JsonResponse
    {
        Financement::findOrFail($financementId);
        $items = DecaissementPlan::with(self::PLAN_RELATIONS)
            ->where('financement_id', $financementId)
            ->orderBy('date_prevue')->get();
        return response()->json($items);
    }

    private function planRules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'date_prevue'         => "{$req}|date",
            'exercice_budgetaire' => 'nullable|string|max:20',
            'annee'               => 'nullable|integer|min:2000|max:2100',
            'montant_prevu'       => "{$req}|numeric|min:0",
            'devise'              => 'nullable|exists:currencies,code',
            'statut'              => 'sometimes|in:prevu,effectue',
            'description'         => 'nullable|string',
            'composante_id'       => 'nullable|integer|exists:composantes,id',
            'activite_id'         => 'nullable|integer|exists:activites,id',
            'justificatif'        => 'nullable|file|max:20480',
        ];
    }

    public function storePlan(Request $request, int $financementId): JsonResponse
    {
        $financement = Financement::findOrFail($financementId);

        $this->normalizeEmptyStrings($request, ['exercice_budgetaire', 'annee', 'description', 'composante_id', 'activite_id']);
        $validated = $request->validate($this->planRules(false));
        unset($validated['justificatif']);
        $validated = $this->applyCurrencyDefaults($validated, 'montant_prevu');

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/plans');

        $plan = DecaissementPlan::create([
            ...$validated,
            'financement_id'    => $financementId,
            'justificatif_path' => $path,
            'justificatif_name' => $name,
        ]);

        $this->logService->log('create', 'decaissement_plan', "Budget programmé ajouté : {$plan->montant_prevu} {$plan->devise}", $financement->project_id);

        return response()->json($plan->load(self::PLAN_RELATIONS), 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = DecaissementPlan::findOrFail($id);

        $this->normalizeEmptyStrings($request, ['exercice_budgetaire', 'annee', 'description', 'composante_id', 'activite_id']);
        $validated = $request->validate($this->planRules(true));
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/plans');
        if ($path) {
            $this->deleteJustificatifFile($plan->justificatif_path);
            $validated['justificatif_path'] = $path;
            $validated['justificatif_name'] = $name;
        }

        $plan->update($validated);

        $this->logService->log('update', 'decaissement_plan', "Budget programmé modifié #{$plan->id}", $plan->financement->project_id);

        return response()->json($plan->fresh()->load(self::PLAN_RELATIONS));
    }

    public function destroyPlan(int $id): JsonResponse
    {
        $plan      = DecaissementPlan::findOrFail($id);
        $projectId = $plan->financement->project_id;

        $this->deleteJustificatifFile($plan->justificatif_path);
        $plan->delete();

        $this->logService->log('delete', 'decaissement_plan', "Budget programmé supprimé #{$id}", $projectId);

        return response()->json(['message' => 'Supprimé.']);
    }

    public function downloadPlan(int $id)
    {
        $plan = DecaissementPlan::findOrFail($id);
        return $this->downloadJustificatifResponse($plan->justificatif_path, $plan->justificatif_name);
    }

    // ── DÉCAISSEMENTS RÉELS (étape "Budgets décaissés") ─────────────────────

    public function decaissements(int $financementId): JsonResponse
    {
        Financement::findOrFail($financementId);
        $items = Decaissement::with(self::DECAISSEMENT_RELATIONS)
            ->where('financement_id', $financementId)
            ->orderByDesc('date')->get();
        return response()->json($items);
    }

    private function decaissementRules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'date'          => "{$req}|date",
            'montant'       => "{$req}|numeric|min:0",
            'devise'        => 'nullable|exists:currencies,code',
            'reference'     => 'nullable|string|max:255',
            'beneficiaire'  => 'nullable|string|max:255',
            'commentaire'   => 'nullable|string',
            'composante_id' => 'nullable|integer|exists:composantes,id',
            'activite_id'   => 'nullable|integer|exists:activites,id',
            'justificatif'  => 'nullable|file|max:20480',
        ];
    }

    public function storeDecaissement(Request $request, int $financementId): JsonResponse
    {
        $financement = Financement::findOrFail($financementId);

        $this->normalizeEmptyStrings($request, ['reference', 'beneficiaire', 'commentaire', 'composante_id', 'activite_id']);
        $validated = $request->validate($this->decaissementRules(false));
        unset($validated['justificatif']);
        $validated = $this->applyCurrencyDefaults($validated, 'montant');

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/decaissements');

        $dec = Decaissement::create([
            ...$validated,
            'financement_id'    => $financementId,
            'justificatif_path' => $path,
            'justificatif_name' => $name,
        ]);

        $this->logService->log('create', 'decaissement', "Déblocage ajouté : {$dec->montant} {$dec->devise}", $financement->project_id);

        return response()->json($dec->load(self::DECAISSEMENT_RELATIONS), 201);
    }

    public function updateDecaissement(Request $request, int $id): JsonResponse
    {
        $dec = Decaissement::findOrFail($id);

        $this->normalizeEmptyStrings($request, ['reference', 'beneficiaire', 'commentaire', 'composante_id', 'activite_id']);
        $validated = $request->validate($this->decaissementRules(true));
        unset($validated['justificatif']);

        [$path, $name] = $this->storeJustificatif($request, 'justificatif', 'budgets/decaissements');
        if ($path) {
            $this->deleteJustificatifFile($dec->justificatif_path);
            $validated['justificatif_path'] = $path;
            $validated['justificatif_name'] = $name;
        }

        $dec->update($validated);

        $this->logService->log('update', 'decaissement', "Déblocage modifié #{$dec->id}", $dec->financement->project_id);

        return response()->json($dec->fresh()->load(self::DECAISSEMENT_RELATIONS));
    }

    public function destroyDecaissement(int $id): JsonResponse
    {
        $dec       = Decaissement::findOrFail($id);
        $projectId = $dec->financement->project_id;

        $this->deleteJustificatifFile($dec->justificatif_path);
        $dec->delete();

        $this->logService->log('delete', 'decaissement', "Déblocage supprimé #{$id}", $projectId);

        return response()->json(['message' => 'Supprimé.']);
    }

    public function downloadDecaissement(int $id)
    {
        $dec = Decaissement::findOrFail($id);
        return $this->downloadJustificatifResponse($dec->justificatif_path, $dec->justificatif_name);
    }

    // ── DÉPENSES DU PROJET (lecture seule) ───────────────────────────────────

    public function projectDepenses(Request $request, int $projectId): JsonResponse
    {
        $depenses = Depense::with('financement')
            ->where('project_id', $projectId)
            ->when($request->filled('financement_id'),
                fn ($q) => $q->where('financement_id', $request->integer('financement_id')))
            ->when($request->filled('categorie'),
                fn ($q) => $q->where('categorie', $request->categorie))
            ->when($request->filled('date_from'),
                fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->filled('date_to'),
                fn ($q) => $q->whereDate('date', '<=', $request->date_to))
            ->orderByDesc('date')
            ->get();

        return response()->json($depenses);
    }
}

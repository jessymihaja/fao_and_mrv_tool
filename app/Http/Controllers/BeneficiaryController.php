<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD « Bénéficiaires du projet ». Le project_id est TOUJOURS déduit de
 * la route (/projects/{project}/beneficiaries, /beneficiaries/{id}) —
 * jamais accepté depuis le payload (protection IDOR).
 */
class BeneficiaryController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // GET /projects/{project}/beneficiaries
    public function byProject(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);

        $items = Beneficiary::with(['beneficiaryType', 'beneficiaryCategory', 'region:id,nom', 'district:id,nom', 'commune:id,nom', 'fokontany:id,nom'])
            ->where('project_id', $projectId)
            ->when($request->filled('beneficiary_type_id'),     fn ($q) => $q->where('beneficiary_type_id', $request->beneficiary_type_id))
            ->when($request->filled('beneficiary_category_id'), fn ($q) => $q->where('beneficiary_category_id', $request->beneficiary_category_id))
            ->orderByDesc('created_at')
            ->get();

        // Statistiques synthétiques calculées côté serveur à partir des
        // données enregistrées — jamais saisies manuellement (cahier des
        // charges §12).
        $stats = [
            'total_prevu'      => (int) $items->sum('planned_count'),
            'total_atteint'    => (int) $items->sum('achieved_count'),
            'femmes'           => (int) $items->sum('women_count'),
            'hommes'           => (int) $items->sum('men_count'),
            'jeunes'           => (int) $items->sum('youth_count'),
            'vulnerables'      => (int) $items->sum('vulnerable_count'),
            'taux_atteinte'    => $items->sum('planned_count') > 0
                ? round(($items->sum('achieved_count') / $items->sum('planned_count')) * 100, 2)
                : 0,
        ];

        return response()->json(['data' => $items, 'stats' => $stats]);
    }

    private function validationRules(bool $isUpdate, ?int $projectId): array
    {
        $prefix = $isUpdate ? 'sometimes|' : '';

        return [
            'beneficiary_type_id'     => $prefix . 'required|integer|exists:beneficiary_types,id',
            'beneficiary_category_id' => $prefix . 'required|integer|exists:beneficiary_categories,id',
            'description'      => 'nullable|string',
            'region_id'        => 'nullable|integer|exists:regions,id',
            'district_id'      => 'nullable|integer|exists:districts,id',
            'commune_id'       => 'nullable|integer|exists:communes,id',
            'fokontany_id'     => 'nullable|integer|exists:fokontany,id',
            'planned_count'    => $prefix . 'required|integer|min:0',
            'achieved_count'   => $prefix . 'required|integer|min:0',
            'women_count'      => 'nullable|integer|min:0',
            'men_count'        => 'nullable|integer|min:0',
            'youth_count'      => 'nullable|integer|min:0',
            'vulnerable_count' => 'nullable|integer|min:0',
            'reference_year'   => $prefix . 'required|integer|digits:4|min:2000|max:2100',
            'monitoring_year'  => 'nullable|integer|digits:4|min:2000|max:2100',
            'source'           => 'nullable|string|max:255',
            'observations'     => 'nullable|string',
        ];
    }

    /**
     * Femmes + hommes ne doit pas dépasser le nombre atteint lorsque les deux
     * valeurs représentent une désagrégation complète (règle métier §13,
     * doublée en base par une contrainte CHECK — voir migration).
     */
    private function assertGenderBreakdown(array $validated, Beneficiary $current = null): void
    {
        $women   = $validated['women_count']    ?? $current?->women_count;
        $men     = $validated['men_count']      ?? $current?->men_count;
        $achieved = $validated['achieved_count'] ?? $current?->achieved_count ?? 0;

        if ($women !== null && $men !== null && ($women + $men) > $achieved) {
            throw ValidationException::withMessages([
                'women_count' => "La somme femmes + hommes ne peut pas dépasser le nombre de bénéficiaires atteints.",
            ]);
        }
    }

    // POST /projects/{project}/beneficiaries
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate($this->validationRules(false, $project->id));
        $this->assertGenderBreakdown($validated);

        $validated['project_id'] = $project->id;

        $beneficiary = Beneficiary::create(array_merge($validated, ['created_by' => $request->user()->id]));

        $this->logService->log('create', 'beneficiary', "Bénéficiaire ajouté : {$beneficiary->beneficiaryCategory?->designation} ({$beneficiary->beneficiaryType?->designation})", $project->id);

        return response()->json(
            $beneficiary->load(['beneficiaryType', 'beneficiaryCategory', 'region:id,nom', 'district:id,nom', 'commune:id,nom', 'fokontany:id,nom']),
            201
        );
    }

    // GET /beneficiaries/{id}
    public function show(int $id): JsonResponse
    {
        $beneficiary = Beneficiary::with(['beneficiaryType', 'beneficiaryCategory', 'region:id,nom', 'district:id,nom', 'commune:id,nom', 'fokontany:id,nom', 'project:id,titre'])
            ->findOrFail($id);

        return response()->json($beneficiary);
    }

    // PUT /beneficiaries/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $beneficiary = Beneficiary::findOrFail($id);

        $validated = $request->validate($this->validationRules(true, $beneficiary->project_id));
        $this->assertGenderBreakdown($validated, $beneficiary);

        // project_id n'est jamais modifiable depuis ce endpoint.
        unset($validated['project_id']);

        $beneficiary->update($validated);

        $this->logService->log('update', 'beneficiary', "Bénéficiaire modifié : {$beneficiary->beneficiaryCategory?->designation} ({$beneficiary->beneficiaryType?->designation})", $beneficiary->project_id);

        return response()->json(
            $beneficiary->fresh()->load(['beneficiaryType', 'beneficiaryCategory', 'region:id,nom', 'district:id,nom', 'commune:id,nom', 'fokontany:id,nom'])
        );
    }

    // DELETE /beneficiaries/{id}
    public function destroy(int $id): JsonResponse
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $projectId   = $beneficiary->project_id;

        $beneficiary->delete();

        $this->logService->log('delete', 'beneficiary', "Bénéficiaire supprimé (#{$id})", $projectId);

        return response()->json(['message' => 'Bénéficiaire supprimé avec succès.']);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Composante;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComposanteController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // GET /projects/{project}/composantes
    public function byProject(int $projectId, Request $request): JsonResponse
    {
        $composantes = Composante::with(['activites', 'indicateurs', 'documents'])
            ->withCount(['activites', 'indicateurs', 'documents'])
            ->where('project_id', $projectId)
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($s) => $s->where('nom', 'ilike', "%{$request->search}%")
                             ->orWhere('code', 'ilike', "%{$request->search}%")
            ))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderBy('ordre')
            ->orderBy('created_at')
            ->get();

        return response()->json($composantes);
    }

    private function validationRules(bool $isUpdate = false): array
    {
        $prefix = $isUpdate ? 'sometimes|' : '';

        return [
            'code'                => 'nullable|string|max:50',
            'nom'                 => $prefix . 'required|string|max:255',
            'objectif_specifique' => 'nullable|string|max:500',
            'description'         => 'nullable|string',
            'responsable'         => 'nullable|string|max:255',
            'budget'              => 'nullable|numeric|min:0',
            'devise'              => 'nullable|exists:currencies,code',
            'date_debut'          => 'nullable|date',
            'date_fin'            => 'nullable|date|after_or_equal:date_debut',
            'statut'              => 'nullable|in:Planifiee,En cours,Terminee,Suspendue',
            'ordre'               => 'nullable|integer|min:0',
        ];
    }

    // POST /projects/{project}/composantes
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate($this->validationRules(false));
        $validated['project_id'] = $project->id;
        $validated['statut'] = $validated['statut'] ?? 'Planifiee';
        // Même convention que pour les autres montants du système (voir
        // HandlesJustificatifUploads::applyCurrencyDefaults) : un budget
        // sans devise explicite est en Ariary par défaut.
        if (!empty($validated['budget']) && empty($validated['devise'])) {
            $validated['devise'] = 'AR';
        }

        $composante = Composante::create($validated);

        $this->logService->log('create', 'composante', "Composante créée : {$composante->nom}", $project->id);

        return response()->json(
            $composante->loadCount(['activites', 'indicateurs', 'documents']),
            201
        );
    }

    // GET /composantes/{id}
    public function show(int $id): JsonResponse
    {
        $composante = Composante::with(['activites', 'indicateurs', 'documents'])
            ->findOrFail($id);

        return response()->json($composante);
    }

    // PUT /composantes/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $composante = Composante::findOrFail($id);

        $validated = $request->validate($this->validationRules(true));
        if (array_key_exists('budget', $validated) && $validated['budget'] !== null
            && empty($validated['devise']) && empty($composante->devise)) {
            $validated['devise'] = 'AR';
        }
        $composante->update($validated);

        $this->logService->log('update', 'composante', "Composante modifiée : {$composante->nom}", $composante->project_id);

        return response()->json($composante->fresh()->loadCount(['activites', 'indicateurs', 'documents']));
    }

    // DELETE /composantes/{id}
    public function destroy(int $id): JsonResponse
    {
        $composante = Composante::findOrFail($id);
        $nom        = $composante->nom;
        $projectId  = $composante->project_id;

        $composante->delete(); // cascade → activités, indicateurs, documents rattachés

        $this->logService->log('delete', 'composante', "Composante supprimée : {$nom}", $projectId);

        return response()->json(['message' => 'Composante supprimée avec succès.']);
    }
}

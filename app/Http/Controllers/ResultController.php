<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Composante;
use App\Models\Indicateur;
use App\Models\Project;
use App\Models\Result;
use App\Models\ResultPieceJointe;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD « Résultats du projet ». Le project_id est TOUJOURS déduit de la
 * route (/projects/{project}/results, /results/{id}) — jamais accepté
 * depuis le payload, pour empêcher un utilisateur de rattacher un
 * résultat à un autre projet que celui réellement ouvert (IDOR).
 */
class ResultController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    private const STATUTS = ['prevu', 'en_cours', 'atteint', 'partiellement_atteint', 'non_atteint'];

    // GET /projects/{project}/results
    public function byProject(int $projectId, Request $request): JsonResponse
    {
        Project::findOrFail($projectId);

        $results = Result::with(['resultType', 'indicateur:id,nom,unite,valeur_cible,valeur_realisee,taux_atteinte,date_reference', 'composante:id,nom', 'activite:id,nom', 'piecesJointes'])
            ->where('project_id', $projectId)
            ->when($request->filled('result_type_id'), fn ($q) => $q->where('result_type_id', $request->result_type_id))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('search'), fn ($q) => $q->where('titre', 'ilike', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->get();

        return response()->json($results);
    }

    private function validationRules(bool $isUpdate, ?int $projectId): array
    {
        $prefix = $isUpdate ? 'sometimes|' : '';

        return [
            'result_type_id'       => $prefix . 'required|integer|exists:result_types,id',
            'titre'                => $prefix . 'required|string|max:255',
            'description'          => 'nullable|string',
            'composante_id'        => ['nullable', 'integer', Rule::exists('composantes', 'id')->where('project_id', $projectId)],
            'activite_id'          => 'nullable|integer|exists:activites,id',
            'indicateur_id'        => 'nullable|integer|exists:indicateurs,id',
            'reference_year'       => $prefix . 'required|integer|digits:4|min:2000|max:2100',
            'target_year'          => $prefix . 'required|integer|digits:4|min:2000|max:2100',
            'statut'               => 'nullable|in:' . implode(',', self::STATUTS),
            'valeur_reference'     => 'nullable|numeric|min:0',
            'source_verification'  => 'nullable|string|max:255',
            'methode_collecte'     => 'nullable|string|max:255',
            'observations'         => 'nullable|string',
            'pieces_jointes'       => 'nullable|array',
            'pieces_jointes.*'     => 'file|max:20480|mimes:pdf,jpg,jpeg,png,xlsx,docx',
        ];
    }

    /**
     * Vérifie que l'indicateur / la composante / l'activité éventuellement
     * fournis appartiennent bien au projet concerné — sans cette vérification,
     * un utilisateur pourrait rattacher le résultat d'un projet à un
     * indicateur d'un autre projet (IDOR au niveau des relations).
     */
    private function assertBelongsToProject(array $validated, int $projectId): void
    {
        if (!empty($validated['activite_id'])) {
            $activite = Activite::findOrFail($validated['activite_id']);
            if ($activite->resolveProjectId() !== $projectId) {
                throw ValidationException::withMessages(['activite_id' => "Cette activité n'appartient pas à ce projet."]);
            }
        }

        if (!empty($validated['indicateur_id'])) {
            $indicateur = Indicateur::findOrFail($validated['indicateur_id']);
            if ((int) $indicateur->project_id !== $projectId) {
                throw ValidationException::withMessages(['indicateur_id' => "Cet indicateur n'appartient pas à ce projet."]);
            }
        }
    }

    private function storePieces(Request $request, Result $result): void
    {
        if ($request->hasFile('pieces_jointes')) {
            foreach ($request->file('pieces_jointes') as $file) {
                $path = $file->store("results/{$result->id}", 'local');
                $result->piecesJointes()->create([
                    'fichier'      => $path,
                    'nom_original' => $file->getClientOriginalName(),
                    'taille'       => $file->getSize(),
                    'mime_type'    => $file->getMimeType(),
                ]);
            }
        }
    }

    // POST /projects/{project}/results
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate($this->validationRules(false, $project->id));
        $this->assertBelongsToProject($validated, $project->id);

        $validated['project_id'] = $project->id;
        $validated['statut']     = $validated['statut'] ?? 'prevu';

        if (($validated['target_year'] ?? null) !== null && $validated['target_year'] < $validated['reference_year']) {
            throw ValidationException::withMessages(['target_year' => "L'année cible doit être supérieure ou égale à l'année de référence."]);
        }

        $result = Result::create(array_merge($validated, ['created_by' => $request->user()->id]));

        $this->storePieces($request, $result);

        $this->logService->log('create', 'result', "Résultat créé : {$result->titre}", $project->id);

        return response()->json(
            $result->load(['resultType', 'indicateur', 'composante:id,nom', 'activite:id,nom', 'piecesJointes']),
            201
        );
    }

    // GET /results/{id}
    public function show(int $id): JsonResponse
    {
        $result = Result::with(['resultType', 'indicateur', 'composante:id,nom', 'activite:id,nom', 'piecesJointes', 'project:id,titre'])
            ->findOrFail($id);

        return response()->json($result);
    }

    // PUT/POST /results/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $result = Result::findOrFail($id);

        $validated = $request->validate($this->validationRules(true, $result->project_id));
        $this->assertBelongsToProject($validated, $result->project_id);

        $refYear    = $validated['reference_year'] ?? $result->reference_year;
        $targetYear = $validated['target_year']    ?? $result->target_year;
        if ($targetYear < $refYear) {
            throw ValidationException::withMessages(['target_year' => "L'année cible doit être supérieure ou égale à l'année de référence."]);
        }

        // project_id n'est jamais modifiable depuis ce endpoint : le résultat
        // reste rattaché au projet sur lequel il a été créé.
        unset($validated['project_id']);

        $result->update($validated);

        $this->storePieces($request, $result);

        $this->logService->log('update', 'result', "Résultat modifié : {$result->titre}", $result->project_id);

        return response()->json(
            $result->fresh()->load(['resultType', 'indicateur', 'composante:id,nom', 'activite:id,nom', 'piecesJointes'])
        );
    }

    // DELETE /results/{id}
    public function destroy(int $id): JsonResponse
    {
        $result    = Result::findOrFail($id);
        $titre     = $result->titre;
        $projectId = $result->project_id;

        foreach ($result->piecesJointes as $p) {
            Storage::disk('local')->delete($p->fichier);
        }
        $result->delete();

        $this->logService->log('delete', 'result', "Résultat supprimé : {$titre}", $projectId);

        return response()->json(['message' => 'Résultat supprimé avec succès.']);
    }

    // DELETE /results/{result}/pieces-jointes/{piece}
    public function destroyPieceJointe(int $resultId, int $pieceId): JsonResponse
    {
        $piece = ResultPieceJointe::findOrFail($pieceId);
        abort_if($piece->result_id !== $resultId, 404);

        Storage::disk('local')->delete($piece->fichier);
        $piece->delete();

        return response()->json(null, 204);
    }
}

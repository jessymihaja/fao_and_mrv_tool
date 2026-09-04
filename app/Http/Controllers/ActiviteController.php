<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Composante;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActiviteController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // GET /projects/{project}/activites  (activités rattachées directement au projet)
    public function byProject(int $projectId): JsonResponse
    {
        $activites = Activite::with('piecesJointes')
            ->withCount('indicateurs')
            ->where('project_id', $projectId)
            ->whereNull('composante_id')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($activites);
    }

    // GET /projects/{project}/activites-all  (TOUTES les activités du projet :
    // rattachées directement au projet OU via une composante du projet).
    // À utiliser pour les sélecteurs "Activité" hors gestion CRUD dédiée
    // (cycle budgétaire, résultats, etc.), car la hiérarchie Composante →
    // Activité est le cas le plus courant et ne doit pas être exclue.
    public function allForProject(int $projectId): JsonResponse
    {
        $activites = Activite::with('piecesJointes')
            ->withCount('indicateurs')
            ->where(function ($query) use ($projectId) {
                $query->where('project_id', $projectId)
                    ->orWhereHas('composante', fn ($q) => $q->where('project_id', $projectId));
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($activites);
    }

    // GET /composantes/{composante}/activites
    public function byComposante(int $composanteId): JsonResponse
    {
        $activites = Activite::with('piecesJointes')
            ->withCount('indicateurs')
            ->where('composante_id', $composanteId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($activites);
    }

    private function validationRules(bool $isUpdate = false): array
    {
        $prefix = $isUpdate ? 'sometimes|' : '';

        return [
            'code'                    => 'nullable|string|max:50',
            'nom'                     => $prefix . 'required|string|max:255',
            'description'             => 'nullable|string',
            'responsable'             => 'nullable|string|max:255',
            'date_debut'              => 'nullable|date',
            'date_fin'                => 'nullable|date|after_or_equal:date_debut',
            'budget'                  => 'nullable|numeric|min:0',
            'devise'                  => 'nullable|exists:currencies,code',
            'statut'                  => 'nullable|in:Planifiee,En cours,Terminee,Suspendue',
            'pourcentage_avancement'  => 'nullable|integer|min:0|max:100',
            'observations'            => 'nullable|string',
            'pieces_jointes'          => 'nullable|array',
            'pieces_jointes.*'        => 'file|max:20480',
            'lien'                    => 'nullable|string|max:255',
        ];
    }

    private function storeFiles(Request $request, Activite $activite, Request|null $req = null): void
    {
        if ($request->hasFile('pieces_jointes')) {
            foreach ($request->file('pieces_jointes') as $file) {
                $path = $file->store("activites/{$activite->id}", 'local');
                $activite->piecesJointes()->create([
                    'fichier'          => $path,
                    'fichier_original' => $file->getClientOriginalName(),
                    'taille'           => $file->getSize(),
                    'mime_type'        => $file->getMimeType(),
                    'uploaded_by'      => $request->user()?->id,
                ]);
            }
        }
    }

    // POST /composantes/{composante}/activites
    public function store(Request $request, int $composanteId): JsonResponse
    {
        $composante = Composante::findOrFail($composanteId);
        $activite   = $this->createActivite($request, ['composante_id' => $composante->id]);

        $this->logService->log('create', 'activite', "Activité créée : {$activite->nom}", $composante->project_id);

        return response()->json($activite->load('piecesJointes'), 201);
    }

    // POST /projects/{project}/activites  (activité rattachée directement au projet)
    public function storeForProject(Request $request, int $projectId): JsonResponse
    {
        $project  = \App\Models\Project::findOrFail($projectId);
        $activite = $this->createActivite($request, ['project_id' => $project->id]);

        $this->logService->log('create', 'activite', "Activité (projet) créée : {$activite->nom}", $project->id);

        return response()->json($activite->load('piecesJointes'), 201);
    }

    /**
     * Les champs optionnels arrivent en chaîne vide '' depuis le FormData du
     * frontend (budget, dates non renseignées). Sans cette normalisation,
     * 'nullable|numeric' laisse passer '' telle quelle, qui est ensuite stockée
     * en base puis fait planter le cast decimal:2 du modèle à la relecture
     * (Illuminate\Support\Exceptions\MathException: Unable to cast value to a decimal).
     */
    private function normalizeEmptyStrings(Request $request): void
    {
        $fields = ['budget', 'devise', 'date_debut', 'date_fin', 'code', 'description', 'responsable', 'observations','nom','lien'];
        $merge = [];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $merge[$field] = null;
            }
        }
        if ($merge) {
            $request->merge($merge);
        }
    }

    private function createActivite(Request $request, array $parent): Activite
    {
        $this->normalizeEmptyStrings($request);
        $validated = $request->validate($this->validationRules(false));
        unset($validated['pieces_jointes']);
        $validated = array_merge($validated, $parent);
        $validated['statut'] = $validated['statut'] ?? 'Planifiee';
        $validated['pourcentage_avancement'] = $validated['pourcentage_avancement'] ?? 0;
        if (!empty($validated['budget']) && empty($validated['devise'])) {
            $validated['devise'] = 'AR';
        }

        // Transaction : si l'enregistrement d'une pièce jointe échoue après
        // que d'autres aient déjà été créées, l'activité ne doit pas rester
        // avec un sous-ensemble partiel de ses pièces jointes.
        return DB::transaction(function () use ($request, $validated) {
            $activite = Activite::create($validated);
            $this->storeFiles($request, $activite);

            return $activite;
        });
    }

    // GET /activites/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(Activite::with('piecesJointes')->findOrFail($id));
    }

    // POST /activites/{id} (multipart update, fallback pour l'upload de fichiers)
    public function update(Request $request, int $id): JsonResponse
    {
        $this->normalizeEmptyStrings($request);
        $validated = $request->validate($this->validationRules(true));
        unset($validated['pieces_jointes']);

        $activite = DB::transaction(function () use ($request, $id, $validated) {
            $activite = Activite::lockForUpdate()->findOrFail($id);
            if (array_key_exists('budget', $validated) && $validated['budget'] !== null
                && empty($validated['devise']) && empty($activite->devise)) {
                $validated['devise'] = 'AR';
            }
            $activite->update($validated);
            $this->storeFiles($request, $activite);

            return $activite;
        });

        $this->logService->log('update', 'activite', "Activité modifiée : {$activite->nom}", $activite->resolveProjectId());

        return response()->json($activite->fresh()->load('piecesJointes'));
    }

    // DELETE /activites/{id}
    public function destroy(int $id): JsonResponse
    {
        $activite = Activite::findOrFail($id);
        $nom      = $activite->nom;
        $projectId = $activite->resolveProjectId();

        foreach ($activite->piecesJointes as $pj) {
            Storage::disk('local')->delete($pj->fichier);
        }
        $activite->delete();

        $this->logService->log('delete', 'activite', "Activité supprimée : {$nom}", $projectId);

        return response()->json(['message' => 'Activité supprimée avec succès.']);
    }

    // DELETE /activites/{activite}/pieces-jointes/{piece}
    public function destroyPieceJointe(int $activiteId, int $pieceId): JsonResponse
    {
        $activite = Activite::findOrFail($activiteId);
        $piece    = $activite->piecesJointes()->findOrFail($pieceId);

        Storage::disk('local')->delete($piece->fichier);
        $piece->delete();

        return response()->json(['message' => 'Pièce jointe supprimée.']);
    }
}
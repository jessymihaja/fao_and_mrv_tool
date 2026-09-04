<?php

namespace App\Http\Controllers;

use App\Models\ProjectIdea;
use App\Models\ProjectIdeaFinancement;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectIdeaFinancementController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    private const RELATIONS = ['organismeContributeur'];

    public function index(int $ideaId): JsonResponse
    {
        ProjectIdea::findOrFail($ideaId);
        $items = ProjectIdeaFinancement::with(self::RELATIONS)->where('project_idea_id', $ideaId)->get();

        return response()->json($items);
    }

    private function rules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'organisme_contributeur_id' => 'nullable|integer|exists:organismes_contributeurs,id',
            'bailleur_autre'            => 'nullable|string|max:255',
            'montant_demande'           => 'nullable|numeric|min:0',
            'devise'                    => "{$req}|exists:currencies,code",
            'type_financement'          => "{$req}|in:don,pret,cofinancement,assistance_technique",
            'statut'                    => "{$req}|in:en_preparation,soumis,en_negociation",
        ];
    }

    private function normalizeEmptyStrings(Request $request, array $fields): void
    {
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

    public function store(Request $request, int $ideaId): JsonResponse
    {
        $idea = ProjectIdea::findOrFail($ideaId);

        $this->normalizeEmptyStrings($request, ['organisme_contributeur_id', 'bailleur_autre', 'montant_demande']);
        $validated = $request->validate($this->rules(false));

        if (empty($validated['organisme_contributeur_id']) && empty($validated['bailleur_autre'])) {
            return response()->json(['message' => 'Indiquez un organisme référencé ou un nom de bailleur.'], 422);
        }

        $financement = ProjectIdeaFinancement::create(['project_idea_id' => $idea->id, ...$validated]);

        $this->logService->log('create', 'project_idea_financement', "Bailleur envisagé ajouté pour : {$idea->titre}", null);

        return response()->json($financement->load(self::RELATIONS), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $financement = ProjectIdeaFinancement::findOrFail($id);

        $this->normalizeEmptyStrings($request, ['organisme_contributeur_id', 'bailleur_autre', 'montant_demande']);
        $validated = $request->validate($this->rules(true));

        $financement->update($validated);

        $this->logService->log('update', 'project_idea_financement', "Bailleur envisagé modifié #{$financement->id}", null);

        return response()->json($financement->fresh()->load(self::RELATIONS));
    }

    public function destroy(int $id): JsonResponse
    {
        $financement = ProjectIdeaFinancement::findOrFail($id);
        $financement->delete();

        $this->logService->log('delete', 'project_idea_financement', "Bailleur envisagé supprimé #{$id}", null);

        return response()->json(['message' => 'Supprimé.']);
    }
}

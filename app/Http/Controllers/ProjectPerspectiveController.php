<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPerspective;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectPerspectiveController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    private const RELATIONS = ['type'];

    public function index(int $projectId): JsonResponse
    {
        Project::findOrFail($projectId);

        return response()->json(
            ProjectPerspective::with(self::RELATIONS)->where('project_id', $projectId)->orderByDesc('created_at')->get()
        );
    }

    /** Toutes les perspectives, tous projets confondus — alimente la page publique dédiée. */
    public function publicIndex(Request $request): JsonResponse
    {
        $perspectives = ProjectPerspective::with([...self::RELATIONS, 'project:id,titre,is_published'])
            ->whereHas('project', fn ($q) => $q->where('is_published', true))
            ->when($request->filled('type_id'), fn ($q) => $q->where('type_id', $request->integer('type_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return response()->json($perspectives);
    }

    private function nullableFields(): array
    {
        return ['description', 'zone_extension_envisagee', 'objectif_moyen_terme', 'objectif_long_terme', 'impact_futur_attendu'];
    }

    private function sanitize(Request $request): void
    {
        $data = $request->all();
        foreach ($this->nullableFields() as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $request->replace($data);
    }

    private function rules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'type_id'                   => "{$req}|integer|exists:perspective_types,id",
            'titre'                     => "{$req}|string|max:255",
            'description'               => 'nullable|string',
            'zone_extension_envisagee'  => 'nullable|string|max:255',
            'objectif_moyen_terme'      => 'nullable|string',
            'objectif_long_terme'       => 'nullable|string',
            'impact_futur_attendu'      => 'nullable|string',
            'statut'                    => 'sometimes|in:a_l_etude,planifie,en_cours,realise',
        ];
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $this->sanitize($request);
        $validated = $request->validate($this->rules(false));

        $perspective = ProjectPerspective::create([
            ...$validated,
            'project_id' => $project->id,
            'statut'     => $validated['statut'] ?? 'a_l_etude',
            'created_by' => $request->user()?->id,
        ]);

        $this->logService->log('create', 'project_perspective', "Perspective ajoutée pour : {$project->titre}", $project->id);

        return response()->json($perspective->load(self::RELATIONS), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $perspective = ProjectPerspective::findOrFail($id);

        $this->sanitize($request);
        $validated = $request->validate($this->rules(true));

        $perspective->update($validated);

        $this->logService->log('update', 'project_perspective', "Perspective modifiée #{$perspective->id}", $perspective->project_id);

        return response()->json($perspective->fresh()->load(self::RELATIONS));
    }

    public function destroy(int $id): JsonResponse
    {
        $perspective = ProjectPerspective::findOrFail($id);
        $projectId = $perspective->project_id;
        $perspective->delete();

        $this->logService->log('delete', 'project_perspective', "Perspective supprimée #{$id}", $projectId);

        return response()->json(['message' => 'Perspective supprimée.']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectIdeaResource;
use App\Models\ProjectIdea;
use App\Models\ProjectIdeaStatusHistory;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProjectIdeaController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    private const LIST_RELATIONS   = ['secteurs', 'financements.organismeContributeur', 'region', 'province'];
    private const DETAIL_RELATIONS = [
        'province', 'region', 'district', 'commune', 'fokontany',
        'secteurs', 'financements.organismeContributeur', 'documents', 'statusHistory.auteur', 'creator', 'convertedProject',
    ];

    /**
     * 'ilike' (insensible à la casse) n'existe que sous PostgreSQL — en
     * développement/tests sous SQLite on retombe sur 'like', déjà
     * insensible à la casse par défaut pour l'ASCII sous SQLite.
     */
    private function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $op = $this->likeOperator();

        $ideas = ProjectIdea::with(self::LIST_RELATIONS)
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($s) => $s->where('titre', $op, "%{$request->search}%")
                             ->orWhere('acronyme', $op, "%{$request->search}%")
                             ->orWhere('porteur_projet', $op, "%{$request->search}%")
            ))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('secteur_id'), fn ($q) => $q->whereHas(
                'secteurs', fn ($s) => $s->where('secteurs.id', $request->integer('secteur_id'))
            ))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('bailleur_id'), fn ($q) => $q->whereHas(
                'financements', fn ($f) => $f->where('organisme_contributeur_id', $request->integer('bailleur_id'))
            ))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ProjectIdeaResource::collection($ideas);
    }

    /** Données non paginées pour l'export (PDF/Excel) côté frontend, mêmes filtres que index(). */
    public function exportData(Request $request)
    {
        $op = $this->likeOperator();

        $ideas = ProjectIdea::with(self::LIST_RELATIONS)
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($s) => $s->where('titre', $op, "%{$request->search}%")
                             ->orWhere('acronyme', $op, "%{$request->search}%")
                             ->orWhere('porteur_projet', $op, "%{$request->search}%")
            ))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get();

        return ProjectIdeaResource::collection($ideas);
    }

    private function nullableFields(): array
    {
        return [
            'acronyme', 'description', 'contexte', 'justification', 'objectif_general',
            'objectifs_specifiques', 'resultats_attendus', 'duree_prevue_mois',
            'date_debut_estimee', 'date_fin_estimee', 'porteur_projet', 'lien',
            'latitude', 'longitude', 'province_id', 'region_id', 'district_id', 'commune_id', 'fokontany_id',
            'zone_description', 'geo_address',
            'nombre_beneficiaires', 'beneficiaires_hommes', 'beneficiaires_femmes',
            'beneficiaires_jeunes', 'beneficiaires_vulnerables',
            'contribution_nationale', 'contribution_partenaires', 'cofinancement_prive', 'autres_financements',
        ];
    }

    /** Chaînes vides '' -> null pour les champs nullable, secteur_ids toujours tableau. */
    private function sanitize(Request $request): void
    {
        $data = $request->all();

        foreach ($this->nullableFields() as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (array_key_exists('secteur_ids', $data) && ! is_array($data['secteur_ids'])) {
            $data['secteur_ids'] = $data['secteur_ids'] === '' || $data['secteur_ids'] === null ? [] : [$data['secteur_ids']];
        }

        $request->replace($data);
    }

    private function rules(bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'titre'                     => "{$req}|string|max:255",
            'lien'                      => 'nullable|url|max:500',
            'acronyme'                  => 'nullable|string|max:50',
            'description'               => 'nullable|string',
            'contexte'                  => 'nullable|string',
            'justification'             => 'nullable|string',
            'objectif_general'          => 'nullable|string',
            'objectifs_specifiques'     => 'nullable|string',
            'resultats_attendus'        => 'nullable|string',
            'duree_prevue_mois'         => 'nullable|integer|min:0|max:600',
            'date_debut_estimee'        => 'nullable|date',
            'date_fin_estimee'          => 'nullable|date|after_or_equal:date_debut_estimee',
            'porteur_projet'            => 'nullable|string|max:255',

            'latitude'                  => 'nullable|numeric|between:-90,90',
            'longitude'                 => 'nullable|numeric|between:-180,180',
            'province_id'               => 'nullable|integer|exists:provinces,id',
            'region_id'                 => 'nullable|integer|exists:regions,id',
            'district_id'               => 'nullable|integer|exists:districts,id',
            'commune_id'                => 'nullable|integer|exists:communes,id',
            'fokontany_id'              => 'nullable|integer|exists:fokontany,id',
            'zone_description'          => 'nullable|string',
            'geo_address'               => 'nullable|string|max:255',

            'secteur_ids'               => 'nullable|array',
            'secteur_ids.*'             => 'integer|exists:secteurs,id',

            'nombre_beneficiaires'      => 'nullable|integer|min:0',
            'beneficiaires_hommes'      => 'nullable|integer|min:0',
            'beneficiaires_femmes'      => 'nullable|integer|min:0',
            'beneficiaires_jeunes'      => 'nullable|integer|min:0',
            'beneficiaires_vulnerables' => 'nullable|integer|min:0',

            'budget_total_estime'       => 'nullable|numeric|min:0',
            'devise'                    => 'nullable|exists:currencies,code',
            'contribution_nationale'    => 'nullable|numeric|min:0',
            'contribution_partenaires'  => 'nullable|numeric|min:0',
            'cofinancement_prive'       => 'nullable|numeric|min:0',
            'autres_financements'       => 'nullable|numeric|min:0',
        ];
    }

    public function store(Request $request)
    {
        $this->sanitize($request);
        $validated = $request->validate($this->rules(false));
        $secteurIds = $validated['secteur_ids'] ?? [];
        unset($validated['secteur_ids']);

        $idea = ProjectIdea::create([
            ...$validated,
            'statut'     => 'brouillon',
            'created_by' => $request->user()?->id,
        ]);
        $idea->secteurs()->sync($secteurIds);

        ProjectIdeaStatusHistory::create([
            'project_idea_id' => $idea->id,
            'ancien_statut'   => null,
            'nouveau_statut'  => 'brouillon',
            'commentaire'     => 'Création de l\'idée de projet',
            'changed_by'      => $request->user()?->id,
        ]);

        $this->logService->log('create', 'project_idea', "Idée de projet créée : {$idea->titre}", null);

        return new ProjectIdeaResource($idea->load(self::DETAIL_RELATIONS));
    }

    public function show(int $id): ProjectIdeaResource
    {
        return new ProjectIdeaResource(ProjectIdea::with(self::DETAIL_RELATIONS)->findOrFail($id));
    }

    public function update(Request $request, int $id): ProjectIdeaResource
    {
        $idea = ProjectIdea::findOrFail($id);

        $this->sanitize($request);
        $validated = $request->validate($this->rules(true));
        $secteurIds = $validated['secteur_ids'] ?? null;
        unset($validated['secteur_ids']);

        $idea->update($validated);
        if ($secteurIds !== null) {
            $idea->secteurs()->sync($secteurIds);
        }

        $this->logService->log('update', 'project_idea', "Idée de projet modifiée : {$idea->titre}", null);

        return new ProjectIdeaResource($idea->fresh()->load(self::DETAIL_RELATIONS));
    }

    public function destroy(int $id)
    {
        $idea = ProjectIdea::findOrFail($id);

        if ($idea->statut === 'converti') {
            return response()->json(['message' => 'Une idée déjà convertie en projet ne peut pas être supprimée.'], 422);
        }

        $titre = $idea->titre;
        $idea->delete();

        $this->logService->log('delete', 'project_idea', "Idée de projet supprimée : {$titre}", null);

        return response()->json(['message' => 'Idée de projet supprimée.']);
    }
}

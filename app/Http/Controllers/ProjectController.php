<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    private const DETAIL_RELATIONS = [
        'province', 'region', 'district',
        'statutRef', 'classifications', 'entitesAccreditees', 'domainesIntervention',
    ];

    // Relations chargées en plus de DETAIL_RELATIONS pour les vues détail
    // (show/store/update) où le polygone de zone doit être affiché.
    private const DETAIL_RELATIONS_WITH_ZONE = [
        ...self::DETAIL_RELATIONS,
        'zonePoints',
        // Zones géographiques multiples (région/district/commune) — voir
        // ProjectGeographicZoneController. `.zoneable` doit être précisé
        // ici pour que ProjectGeographicZone::toDisplayArray() (appelé
        // depuis ProjectResource) n'ait pas besoin de lazy-load (désactivé
        // en dev, voir AppServiceProvider).
        'geographicZones.zoneable',
    ];

    // PUBLIC: Projets publiés
    public function publicIndex(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
        ]);

        $projects = Project::with(['province', 'region', 'financements', 'zonePoints', ...self::DETAIL_RELATIONS])
            ->where('is_published', true)
            ->when($request->filled('status_id'),  fn ($q) => $q->where('status_id', $request->status_id))
            ->when($request->filled('domaine_intervention_id'), fn ($q) => $q->whereHas(
                'domainesIntervention',
                fn ($d) => $d->where('domaine_interventions.id_domaine_intervention', $request->domaine_intervention_id)
            ))
            ->when($request->filled('region_id'),  fn ($q) => $q->where('region_id', $request->integer('region_id')))
            // Recherche globale (titre, code projet, statut, classification,
            // entité accréditée, secteur climatique) — voir
            // Project::scopeSearchGlobal(), source unique de cette logique.
            ->when($request->filled('search'), fn ($q) => $q->searchGlobal($request->string('search')))
            ->orderByDesc('date_debut')
            ->paginate($request->integer('per_page', 12));

        return ProjectResource::collection($projects);
    }

    // PUBLIC: Données carte
public function mapData(): JsonResponse
{
    // Relations nécessaires à la recherche globale sur la carte (voir
    // ProjectSearch / useProjectSearch côté front), chargées en eager
    // loading pour éviter tout N+1.
    $searchRelations = ['statutRef', 'classifications', 'entitesAccreditees', 'domainesIntervention'];

    $projectSearchFields = fn (Project $p) => [
        'id_projet'            => $p->id_projet,
        'classifications'      => $p->classifications->pluck('designation')->values(),
        'entites_accreditees'  => $p->entitesAccreditees->pluck('designation')->values(),
        'domaines_intervention'=> $p->domainesIntervention->pluck('designation')->values(),
    ];

    // Régions avec leurs projets publiés
    $regions = \App\Models\Region::query()
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->with(['projects' => function ($q) use ($searchRelations) {
            $q->where('is_published', true)->with($searchRelations);
        }])
        ->get()
        ->map(fn ($r) => [
            'region_id' => $r->id,
            'region'    => $r->nom,
            'latitude'  => (float) $r->latitude,
            'longitude' => (float) $r->longitude,
            'projects'  => $r->projects->map(fn (Project $p) => [
                'id'     => $p->id,
                'titre'  => $p->titre,
                'statut' => $p->statutRef?->designation,
                ...$projectSearchFields($p),
            ])->values(),
        ])
        ->filter(fn ($r) => $r['projects']->isNotEmpty()) // Garder seulement celles avec projets
        ->values();

    // Projets avec zones géographiques (1 point ou plus), publiés
    $projectZones = Project::query()
        ->where('is_published', true)
        ->whereHas('zonePoints', null, '>=', 1)
        ->with(['zonePoints', ...$searchRelations])
        ->get()
        ->map(fn (Project $p) => [
            'id'          => $p->id,
            'titre'       => $p->titre,
            'statut'      => $p->statutRef?->designation,
            ...$projectSearchFields($p),
            'zone_points' => $p->zonePoints->sortBy('ordre')->map(fn ($pt) => [
                'latitude'  => (float) $pt->latitude,
                'longitude' => (float) $pt->longitude,
            ])->values(),
        ])
        ->values();

    // Projets couverts par des zones géographiques multiples (région,
    // district, commune) — distinct de $projectZones (polygone dessiné à
    // la main) ci-dessus. Un marqueur par association projet ↔ zone.
    $projectGeographicZones = Project::query()
        ->where('is_published', true)
        ->whereHas('geographicZones')
        ->with(['geographicZones.zoneable', ...$searchRelations])
        ->get()
        ->flatMap(fn (Project $p) => $p->geographicZones->map(fn ($gz) => [
            'project_id' => $p->id,
            'titre'      => $p->titre,
            'statut'     => $p->statutRef?->designation,
            ...$projectSearchFields($p),
            ...$gz->toDisplayArray(), // fournit déjà 'id' (id de l'association)
        ]))
        // Une zone ni géocodée elle-même, ni via sa région parente (voir
        // ProjectGeographicZone::toDisplayArray()), ne peut pas être
        // placée sur la carte — on l'exclut plutôt que de planter.
        ->filter(fn ($z) => $z['latitude'] !== null && $z['longitude'] !== null)
        ->values();

    // Calculer le total des projets affichés sur la carte, dédupliqué : un
    // même projet peut apparaître dans plusieurs sources (région,
    // zone_points, zones géographiques multiples).
    $totalProjectsOnMap = collect()
        ->merge($regions->flatMap(fn ($r) => $r['projects']->pluck('id')))
        ->merge($projectZones->pluck('id'))
        ->merge($projectGeographicZones->pluck('project_id'))
        ->unique()
        ->count();

    return response()->json([
        'regions'                  => $regions,
        'project_zones'            => $projectZones,
        'project_geographic_zones' => $projectGeographicZones,
        'total_projects_on_map'    => $totalProjectsOnMap,
    ]);
}

    // PUBLIC: Détail projet
    public function publicShow(int $id): ProjectResource
    {$
        $project = Project::with([
            'province', 'region', 'district', 'commune', 'fokontany',
            'financements', 'documents', 'zonePoints',
            ...self::DETAIL_RELATIONS,
        ])
        ->where('is_published', true)
        ->findOrFail($id);

        return new ProjectResource($project);
    }

    // ADMIN: Liste complète
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::with(['province', 'region', 'district', 'zonePoints', ...self::DETAIL_RELATIONS])
            ->withCount(['financements', 'documents'])
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($s) => $s->where('titre', 'ilike', "%{$request->search}%")
                             ->orWhere('description', 'ilike', "%{$request->search}%")
            ))
            ->when($request->filled('status_id'),  fn ($q) => $q->where('status_id', $request->status_id))
            ->when($request->filled('domaine_intervention_id'), fn ($q) => $q->whereHas(
                'domainesIntervention',
                fn ($d) => $d->where('domaine_interventions.id_domaine_intervention', $request->domaine_intervention_id)
            ))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ProjectResource::collection($projects);
    }

    /**
     * Nettoyer les données avant validation :
     * — chaînes vides "" -> null pour les champs nullable
     * — booléen is_published normalisé
     * — tableaux de sélection multiple (classification_ids, etc.) normalisés
     */
    private function sanitize(Request $request): void
    {
        $nullableFields = [
            'description',
            'date_debut',
            'date_fin',
            'latitude',
            'longitude',
            'province_id',
            'region_id',
            'district_id',
            'commune_id',
            'fokontany_id',
            'zone_description',
            'geo_address',
            'objectifs',
            'impact',
            'problematique_climatique',
            'nombre_beneficiaires',
            'siege'
        ];

        $data = $request->all();

        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (array_key_exists('is_published', $data)) {
            $val = $data['is_published'];
            $data['is_published'] = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        // Les champs multi-select doivent toujours être des tableaux (même vides)
        foreach (['classification_ids', 'entite_accreditee_ids', 'domaine_intervention_ids'] as $field) {
            if (array_key_exists($field, $data) && ! is_array($data[$field])) {
                $data[$field] = $data[$field] === '' || $data[$field] === null ? [] : [$data[$field]];
            }
        }

        // zone_points : normaliser en tableau (jamais null quand la clé est
        // présente) pour permettre de distinguer "champ absent" (ne pas
        // toucher à la zone existante) de "tableau vide" (effacer la zone).
        if (array_key_exists('zone_points', $data) && ! is_array($data['zone_points'])) {
            $data['zone_points'] = [];
        }

        $request->replace($data);
    }

    /**
     * Un polygone de zone doit être soit vide (aucune zone définie), soit
     * comporter au moins 3 sommets — un polygone à 1 ou 2 points n'a pas
     * de sens géométrique. On le vérifie avant la validation des champs
     * individuels pour renvoyer un message clair.
     */
    private function assertValidZonePoints(Request $request): void
    {
        if (! $request->has('zone_points')) {
            return;
        }

        $points = $request->input('zone_points');
        $count  = is_array($points) ? count($points) : 0;

        if ($count > 0 && $count < 3) {
            throw ValidationException::withMessages([
                'zone_points' => ["Impossible de créer une zone de projet : sélectionnez au moins 3 points sur la carte (actuellement {$count})."],
            ]);
        }
    }

    // Règles de validation communes
    private function validationRules(bool $isUpdate = false): array
    {
        $rules = [
            'id_projet'                    => 'nullable|string|max:50|unique:projects,id_projet' . ($isUpdate ? (',' . request()->route('id') ) : ''),
            'titre'                        => 'string|max:255',
            'status_id'                    => 'integer|exists:statuses,id_status',
            'classification_ids'           => 'array',
            'classification_ids.*'         => 'integer|exists:classifications,id_classification',
            'entite_accreditee_ids'        => 'array',
            'entite_accreditee_ids.*'      => 'integer|exists:entite_accreditees,id_entite_accreditee',
            'domaine_intervention_ids'     => 'nullable|array',
            'domaine_intervention_ids.*'   => 'integer|exists:domaine_interventions,id_domaine_intervention',
            'description'                  => 'nullable|string',
            'date_debut'                   => 'nullable|date',
            'date_fin'                     => 'nullable|date|after_or_equal:date_debut',
            'latitude'                     => 'nullable|numeric|between:-90,90',
            'longitude'                    => 'nullable|numeric|between:-180,180',
            'province_id'                  => 'nullable|integer|exists:provinces,id',
            'region_id'                    => 'nullable|integer|exists:regions,id',
            'district_id'                  => 'nullable|integer|exists:districts,id',
            'commune_id'                   => 'nullable|integer|exists:communes,id',
            'fokontany_id'                 => 'nullable|integer|exists:fokontany,id',
            'zone_description'             => 'nullable|string',
            'geo_address'                  => 'nullable|string|max:500',
            'objectifs'                    => 'nullable|string',
            'impact'                       => 'nullable|string',
            'problematique_climatique'     => 'nullable|string',
            'is_published'                 => 'nullable|boolean',
            'nombre_beneficiaires'         => 'nullable|integer|min:0',
            'siege'                        => 'nullable|string|max:255',

            // Polygone de la zone d'intervention (liste ordonnée de sommets)
            'zone_points'                  => 'nullable|array',
            'zone_points.*.latitude'       => 'required|numeric|between:-90,90',
            'zone_points.*.longitude'      => 'required|numeric|between:-180,180',
        ];

        if ($isUpdate) {
            foreach ($rules as $field => $rule) {
                $rules[$field] = 'sometimes|' . $rule;
            }
        } else {
            $rules['titre']                    = 'required|string|max:255';
            $rules['status_id']                = 'required|integer|exists:statuses,id_status';
            $rules['classification_ids']       = 'required|array|min:1';
            $rules['entite_accreditee_ids']    = 'required|array|min:1';
        }

        return $rules;
    }

    /**
     * Remplace les sommets du polygone de zone d'un projet. `null` signifie
     * "champ non fourni" -> on ne touche pas à la zone existante ; un
     * tableau (même vide) remplace entièrement les points existants.
     */
    private function syncZonePoints(Project $project, ?array $points): void
    {
        if ($points === null) {
            return;
        }

        $project->zonePoints()->delete();

        foreach (array_values($points) as $index => $point) {
            $project->zonePoints()->create([
                'latitude'  => $point['latitude'],
                'longitude' => $point['longitude'],
                'ordre'     => $index,
            ]);
        }
    }

    // ADMIN: Créer
    public function store(Request $request): ProjectResource
    {
        $this->sanitize($request);
        $this->assertValidZonePoints($request);
        $validated = $request->validate($this->validationRules(false));
        $validated['is_published'] = $validated['is_published'] ?? false;

        $classificationIds      = $validated['classification_ids'] ?? [];
        $entiteAccrediteeIds    = $validated['entite_accreditee_ids'] ?? [];
        $domaineInterventionIds = $validated['domaine_intervention_ids'] ?? [];
        $zonePoints              = $validated['zone_points'] ?? null;
        unset($validated['classification_ids'], $validated['entite_accreditee_ids'], $validated['domaine_intervention_ids'], $validated['zone_points']);

        // Toute l'opération (projet + pivots + zone + colonnes miroir) est
        // atomique : si une étape échoue, rien n'est persisté (évite un
        // projet créé partiellement sans classifications/zone/entités).
        $project = DB::transaction(function () use ($validated, $classificationIds, $entiteAccrediteeIds, $domaineInterventionIds, $zonePoints) {
            // Auto-générer l'id_projet si non saisi (verrouillage anti-collision
            // dans Project::generateIdProjet(), effectif car on est en transaction)
            if (empty($validated['id_projet'])) {
                $validated['id_projet'] = Project::generateIdProjet();
            }

            $project = Project::create($validated);

            $project->classifications()->sync($classificationIds);
            $project->entitesAccreditees()->sync($entiteAccrediteeIds);
            $project->domainesIntervention()->sync($domaineInterventionIds);
            $this->syncZonePoints($project, $zonePoints);
            $project->syncLegacyFields();

            return $project;
        });

        $this->logService->log('create', 'project', "Projet créé : {$project->titre}", $project->id);

        return new ProjectResource($project->load(self::DETAIL_RELATIONS_WITH_ZONE));
    }

    // ADMIN: Afficher
    public function show(int $id): ProjectResource
    {
        $project = Project::with([
            'province', 'region', 'district', 'commune', 'fokontany',
            'financements', 'documents', 'zonePoints',
            // Zones géographiques multiples (région/district/commune) — sans
            // ça, ProjectResource::whenLoaded('geographicZones', ...) omet
            // complètement la clé du JSON et les marqueurs n'apparaissent
            // jamais sur la carte après un refetch (ex: après ajout d'une
            // zone, invalidation de la query ['project', id]).
            'geographicZones.zoneable',
            ...self::DETAIL_RELATIONS,
        ])->findOrFail($id);

        return new ProjectResource($project);
    }

    // ADMIN: Modifier
    public function update(Request $request, int $id): ProjectResource
    {
        $this->sanitize($request);
        $this->assertValidZonePoints($request);
        $validated = $request->validate($this->validationRules(true));

        $classificationIds      = array_key_exists('classification_ids', $validated) ? $validated['classification_ids'] : null;
        $entiteAccrediteeIds    = array_key_exists('entite_accreditee_ids', $validated) ? $validated['entite_accreditee_ids'] : null;
        $domaineInterventionIds = array_key_exists('domaine_intervention_ids', $validated) ? $validated['domaine_intervention_ids'] : null;
        $zonePoints              = array_key_exists('zone_points', $validated) ? $validated['zone_points'] : null;
        unset($validated['classification_ids'], $validated['entite_accreditee_ids'], $validated['domaine_intervention_ids'], $validated['zone_points']);

        $project = DB::transaction(function () use ($id, $validated, $classificationIds, $entiteAccrediteeIds, $domaineInterventionIds, $zonePoints) {
            $project = Project::lockForUpdate()->findOrFail($id);

            $project->update($validated);

            if ($classificationIds !== null)      $project->classifications()->sync($classificationIds);
            if ($entiteAccrediteeIds !== null)    $project->entitesAccreditees()->sync($entiteAccrediteeIds);
            if ($domaineInterventionIds !== null) $project->domainesIntervention()->sync($domaineInterventionIds);
            $this->syncZonePoints($project, $zonePoints);
            $project->syncLegacyFields();

            return $project;
        });

        $this->logService->log('update', 'project', "Projet modifié : {$project->titre}", $project->id);

        return new ProjectResource($project->load(self::DETAIL_RELATIONS_WITH_ZONE));
    }

    // ADMIN: Valider une étape du Wizard et passer à la suivante
    // Le wizard_step ne peut qu'avancer (jamais reculer via cet endpoint) :
    // il représente la progression la plus loin jamais atteinte par l'utilisateur.
    public function advanceWizardStep(Request $request, int $id): ProjectResource
    {
        $validated = $request->validate([
            'step' => 'required|integer|min:1|max:6',
        ]);

        $project = Project::findOrFail($id);
        $project->advanceWizardStepTo($validated['step']);

        return new ProjectResource($project->fresh());
    }

    // ADMIN: Supprimer
    public function destroy(int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $titre   = $project->titre;
        $project->delete();

        $this->logService->log('delete', 'project', "Projet supprimé : {$titre}");

        return response()->json(['message' => 'Projet supprimé avec succès.']);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\District;
use App\Models\Project;
use App\Models\ProjectGeographicZone;
use App\Models\Region;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des zones géographiques multiples d'un projet (§ "Amélioration de
 * la cartographie des zones géographiques d'un projet") : un projet peut
 * couvrir plusieurs régions/districts/communes, affichées ensemble sur une
 * seule carte. Voir App\Models\ProjectGeographicZone pour l'architecture
 * (relation polymorphe réutilisant les tables Region/District/Commune
 * existantes, sans créer de table générique en doublon).
 */
class ProjectGeographicZoneController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $logService
    ) {}

    // GET /projects/{project}/geographical-zones
    public function index(int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $zones = $project->geographicZones()->with('zoneable')->get()
            ->map(fn (ProjectGeographicZone $z) => $z->toDisplayArray())
            ->values();

        return response()->json($zones);
    }

    private function typeRule(): array
    {
        return ['required', Rule::in(ProjectGeographicZone::TYPES)];
    }

    /**
     * Valide qu'un couple (zone_type, zone_id) référence bien une zone
     * existante et retourne la relation Eloquent correspondante.
     */
    private function resolveZone(string $type, int $id): Region|District|Commune
    {
        $model = match ($type) {
            ProjectGeographicZone::TYPE_REGION   => Region::find($id),
            ProjectGeographicZone::TYPE_DISTRICT => District::find($id),
            ProjectGeographicZone::TYPE_COMMUNE  => Commune::find($id),
        };

        if (! $model) {
            throw ValidationException::withMessages([
                'zone_id' => ["La zone sélectionnée (type: {$type}, id: {$id}) n'existe pas."],
            ]);
        }

        return $model;
    }

    /**
     * POST /projects/{project}/geographical-zones
     *
     * Accepte soit une zone unique :
     *   { "zone_type": "region", "zone_id": 15 }
     * soit un ajout multiple (sélection multiple sur la carte / recherche) :
     *   { "zones": [ { "zone_type": "district", "zone_id": 3 }, ... ] }
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $isBulk = $request->has('zones');

        if ($isBulk) {
            $validated = $request->validate([
                'zones'             => 'required|array|min:1',
                'zones.*.zone_type' => $this->typeRule(),
                'zones.*.zone_id'   => 'required|integer',
            ]);
            $candidates = $validated['zones'];
        } else {
            $validated = $request->validate([
                'zone_type' => $this->typeRule(),
                'zone_id'   => 'required|integer',
            ]);
            $candidates = [$validated];
        }

        $existing = $project->geographicZones()
            ->get(['zoneable_type', 'zoneable_id'])
            ->map(fn ($z) => $z->zoneable_type . ':' . $z->zoneable_id)
            ->all();

        $created = [];
        $skipped = [];

        foreach ($candidates as $candidate) {
            $type = $candidate['zone_type'];
            $id   = (int) $candidate['zone_id'];
            $key  = $type . ':' . $id;

            // Zone déjà existante en base ? (valide l'existence de la
            // référence — §15 "impossible d'associer une zone inexistante")
            $this->resolveZone($type, $id);

            // Doublon déjà associé au projet ? (§15 — pas d'erreur bloquante
            // en mode multiple : on ignore silencieusement le doublon et on
            // continue avec le reste de la sélection)
            if (in_array($key, $existing, true)) {
                $skipped[] = ['zone_type' => $type, 'zone_id' => $id];

                if (! $isBulk) {
                    return response()->json([
                        'message' => 'Cette zone est déjà associée à ce projet.',
                    ], 422);
                }

                continue;
            }

            $zone = $project->geographicZones()->create([
                'zoneable_type' => $type,
                'zoneable_id'   => $id,
            ]);

            $existing[] = $key;
            $created[]  = $zone;
        }

        foreach ($created as $zone) {
            $zone->load('zoneable');
        }

        if (count($created) > 0) {
            $names = collect($created)->map(fn ($z) => $z->toDisplayArray()['name'])->implode(', ');
            $this->logService->log(
                'update',
                'project',
                count($created) > 1
                    ? "Zones géographiques ajoutées au projet : {$names}"
                    : "Zone géographique ajoutée au projet : {$names}",
                $project->id
            );
        }

        return response()->json([
            'created' => collect($created)->map(fn ($z) => $z->toDisplayArray())->values(),
            'skipped' => $skipped,
        ], 201);
    }

    // DELETE /projects/{project}/geographical-zones/{zone}
    public function destroy(int $projectId, int $zoneId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $zone = $project->geographicZones()->with('zoneable')->find($zoneId);

        if (! $zone) {
            return response()->json(['message' => 'Zone géographique introuvable pour ce projet.'], 404);
        }

        $name = $zone->toDisplayArray()['name'];
        $zone->delete();

        $this->logService->log('update', 'project', "Zone géographique retirée du projet : {$name}", $project->id);

        return response()->json(['message' => 'Zone géographique retirée du projet.']);
    }

    /**
     * GET /geo/zones/search?q=...
     *
     * Recherche unifiée région/district/commune (nom, et région/district
     * parent pour le contexte), utilisée par la barre de recherche du
     * sélecteur de zones et par le clic sur la carte.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $like = '%' . $term . '%';

        $regions = Region::query()
            ->where('nom', 'ilike', $like)
            ->limit(10)
            ->get()
            ->map(fn (Region $r) => [
                'zone_type'     => 'region',
                'zone_id'       => $r->id,
                'name'          => $r->nom,
                'region_name'   => $r->nom,
                'district_name' => null,
                'commune_name'  => null,
                'latitude'      => $r->latitude !== null ? (float) $r->latitude : null,
                'longitude'     => $r->longitude !== null ? (float) $r->longitude : null,
            ]);

        $districts = District::query()
            ->with('region')
            ->where('nom', 'ilike', $like)
            ->limit(10)
            ->get()
            ->map(fn (District $d) => [
                'zone_type'     => 'district',
                'zone_id'       => $d->id,
                'name'          => $d->nom,
                'region_name'   => $d->region?->nom,
                'district_name' => $d->nom,
                'commune_name'  => null,
                'latitude'      => $d->latitude !== null ? (float) $d->latitude : ($d->region->latitude ?? null),
                'longitude'     => $d->longitude !== null ? (float) $d->longitude : ($d->region->longitude ?? null),
            ]);

        $communes = Commune::query()
            ->with('district.region')
            ->where('nom', 'ilike', $like)
            ->limit(10)
            ->get()
            ->map(fn (Commune $c) => [
                'zone_type'     => 'commune',
                'zone_id'       => $c->id,
                'name'          => $c->nom,
                'region_name'   => $c->district?->region?->nom,
                'district_name' => $c->district?->nom,
                'commune_name'  => $c->nom,
                'latitude'      => $c->latitude !== null ? (float) $c->latitude : ($c->district->region->latitude ?? null),
                'longitude'     => $c->longitude !== null ? (float) $c->longitude : ($c->district->region->longitude ?? null),
            ]);

        $results = $regions->concat($districts)->concat($communes)->values();

        return response()->json($results);
    }
}

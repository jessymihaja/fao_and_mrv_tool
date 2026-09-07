<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'id_projet'                => $this->id_projet,
            'titre'                    => $this->titre,

            // IDs des FK (pour pré-remplir les selects du formulaire)
            'status_id'                => $this->status_id,
            'classification_ids'       => $this->whenLoaded('classifications', fn () => $this->classifications->pluck('id_classification')->values()),
            'entite_accreditee_ids'    => $this->whenLoaded('entitesAccreditees', fn () => $this->entitesAccreditees->pluck('id_entite_accreditee')->values()),
            'domaine_intervention_ids' => $this->whenLoaded('domainesIntervention', fn () => $this->domainesIntervention->pluck('id_domaine_intervention')->values()),

            // Objets relation (pour affichage) — tableaux (choix multiple)
            'statut'                   => $this->whenLoaded('statutRef', fn () => [
                'id_status'   => $this->statutRef->id_status,
                'designation' => $this->statutRef->designation,
            ]),
            'classifications'          => $this->whenLoaded('classifications', fn () => $this->classifications->map(fn ($c) => [
                'id_classification' => $c->id_classification,
                'designation'       => $c->designation,
            ])->values()),
            'entites_accreditees'      => $this->whenLoaded('entitesAccreditees', fn () => $this->entitesAccreditees->map(fn ($e) => [
                'id_entite_accreditee' => $e->id_entite_accreditee,
                'designation'          => $e->designation,
                'sigle'                => $e->sigle,
            ])->values()),
            'domaines_intervention'    => $this->whenLoaded('domainesIntervention', fn () => $this->domainesIntervention->map(fn ($d) => [
                'id_domaine_intervention' => $d->id_domaine_intervention,
                'designation'             => $d->designation,
            ])->values()),

            'description'              => $this->description,
            'date_debut'               => $this->date_debut?->toDateString(),
            'date_fin'                 => $this->date_fin?->toDateString(),
            'latitude'                 => $this->latitude ? (float) $this->latitude : null,
            'longitude'                => $this->longitude ? (float) $this->longitude : null,
            'province_id'              => $this->province_id,
            'region_id'                => $this->region_id,
            'district_id'              => $this->district_id,
            'commune_id'               => $this->commune_id,
            'fokontany_id'             => $this->fokontany_id,
            'zone_description'         => $this->zone_description,
            'siege'                    => $this->siege,
            'geo_address'              => $this->geo_address,
            // Sommets du polygone de la zone d'intervention, ordonnés — à
            // relier dans cet ordre pour reconstituer le polygone sur la carte.
            'zone_points'              => $this->whenLoaded('zonePoints', fn () => $this->zonePoints->map(fn ($p) => [
                'latitude'  => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
            ])->values()),
            // Zones géographiques multiples (région/district/commune) —
            // distinct du polygone zone_points ci-dessus. Voir
            // ProjectGeographicZoneController.
            'geographic_zones'         => $this->whenLoaded('geographicZones', fn () => $this->geographicZones
                ->map(fn ($z) => $z->toDisplayArray())
                ->values()),
            'objectifs'                => $this->objectifs,
            'impact'                   => $this->impact,
            'problematique_climatique' => $this->problematique_climatique,
            'is_published'             => (bool) $this->is_published,
            'nombre_beneficiaires'     => $this->nombre_beneficiaires,
            'wizard_step'              => (int) $this->wizard_step,
            'province'                 => $this->whenLoaded('province', fn () => [
                'id'  => $this->province->id,
                'nom' => $this->province->nom,
            ]),
            'region'                   => $this->whenLoaded('region', fn () => [
                'id'  => $this->region->id,
                'nom' => $this->region->nom,
            ]),
            'district'                 => $this->whenLoaded('district', fn () => [
                'id'  => $this->district->id,
                'nom' => $this->district->nom,
            ]),
            'commune'                  => $this->whenLoaded('commune', fn () => [
                'id'  => $this->commune->id,
                'nom' => $this->commune->nom,
            ]),
            'fokontany'                => $this->whenLoaded('fokontany', fn () => [
                'id'  => $this->fokontany->id,
                'nom' => $this->fokontany->nom,
            ]),
            'financements'             => $this->whenLoaded('financements'),
            'documents'                => $this->whenLoaded('documents'),
            'financements_count'       => $this->whenCounted('financements'),
            'documents_count'          => $this->whenCounted('documents'),
            'created_at'               => $this->created_at?->toISOString(),
            'updated_at'               => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectIdeaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'titre'                     => $this->titre,
            'lien'                      => $this->lien,
            'acronyme'                  => $this->acronyme,
            'description'               => $this->description,
            'contexte'                  => $this->contexte,
            'justification'             => $this->justification,
            'objectif_general'          => $this->objectif_general,
            'objectifs_specifiques'     => $this->objectifs_specifiques,
            'resultats_attendus'        => $this->resultats_attendus,
            'duree_prevue_mois'         => $this->duree_prevue_mois,
            'date_debut_estimee'        => $this->date_debut_estimee?->toDateString(),
            'date_fin_estimee'          => $this->date_fin_estimee?->toDateString(),
            'porteur_projet'            => $this->porteur_projet,

            'latitude'                  => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'                 => $this->longitude !== null ? (float) $this->longitude : null,
            'province_id'               => $this->province_id,
            'region_id'                 => $this->region_id,
            'district_id'               => $this->district_id,
            'commune_id'                => $this->commune_id,
            'fokontany_id'              => $this->fokontany_id,
            'zone_description'          => $this->zone_description,
            'geo_address'               => $this->geo_address,
            'province'                  => $this->whenLoaded('province',  fn () => ['id' => $this->province->id, 'nom' => $this->province->nom]),
            'region'                    => $this->whenLoaded('region',    fn () => ['id' => $this->region->id, 'nom' => $this->region->nom]),
            'district'                  => $this->whenLoaded('district',  fn () => ['id' => $this->district->id, 'nom' => $this->district->nom]),
            'commune'                   => $this->whenLoaded('commune',   fn () => ['id' => $this->commune->id, 'nom' => $this->commune->nom]),
            'fokontany'                 => $this->whenLoaded('fokontany', fn () => ['id' => $this->fokontany->id, 'nom' => $this->fokontany->nom]),

            'secteur_ids'               => $this->whenLoaded('secteurs', fn () => $this->secteurs->pluck('id')->values()),
            'secteurs'                  => $this->whenLoaded('secteurs', fn () => $this->secteurs->map(fn ($s) => [
                'id' => $s->id, 'designation' => $s->designation,
            ])->values()),

            'nombre_beneficiaires'      => $this->nombre_beneficiaires,
            'beneficiaires_hommes'      => $this->beneficiaires_hommes,
            'beneficiaires_femmes'      => $this->beneficiaires_femmes,
            'beneficiaires_jeunes'      => $this->beneficiaires_jeunes,
            'beneficiaires_vulnerables' => $this->beneficiaires_vulnerables,

            'budget_total_estime'       => $this->budget_total_estime !== null ? (float) $this->budget_total_estime : null,
            'devise'                    => $this->devise,
            'contribution_nationale'    => $this->contribution_nationale !== null ? (float) $this->contribution_nationale : null,
            'contribution_partenaires'  => $this->contribution_partenaires !== null ? (float) $this->contribution_partenaires : null,
            'cofinancement_prive'       => $this->cofinancement_prive !== null ? (float) $this->cofinancement_prive : null,
            'autres_financements'       => $this->autres_financements !== null ? (float) $this->autres_financements : null,
            'total_contributions'       => $this->totalContributions(),
            'pourcentage_cofinancement' => $this->pourcentageCofinancement(),

            'statut'                    => $this->statut,
            'converted_project_id'      => $this->converted_project_id,
            'converted_at'              => $this->converted_at?->toDateTimeString(),

            'financements'              => $this->whenLoaded('financements', fn () => $this->financements->map(fn ($f) => [
                'id'                        => $f->id,
                'organisme_contributeur_id' => $f->organisme_contributeur_id,
                'bailleur'                  => $f->bailleur_label,
                'bailleur_autre'            => $f->bailleur_autre,
                'montant_demande'           => $f->montant_demande !== null ? (float) $f->montant_demande : null,
                'devise'                    => $f->devise,
                'type_financement'          => $f->type_financement,
                'statut'                    => $f->statut,
            ])->values()),
            'bailleur_cible'            => $this->whenLoaded('financements', fn () => $this->financements->first()?->bailleur_label),

            'documents'                 => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id'         => $d->id,
                'type'       => $d->type,
                'libelle'    => $d->libelle,
                'file_name'  => $d->file_name,
                'mime_type'  => $d->mime_type,
                'size'       => $d->size,
                'created_at' => $d->created_at?->toDateTimeString(),
            ])->values()),

            'status_history'            => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'id'             => $h->id,
                'ancien_statut'  => $h->ancien_statut,
                'nouveau_statut' => $h->nouveau_statut,
                'commentaire'    => $h->commentaire,
                'auteur'         => $h->auteur?->name,
                'created_at'     => $h->created_at?->toDateTimeString(),
            ])->values()),

            'created_by'                => $this->created_by,
            'created_at'                => $this->created_at?->toDateTimeString(),
            'updated_at'                => $this->updated_at?->toDateTimeString(),
        ];
    }
}

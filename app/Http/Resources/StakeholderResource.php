<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StakeholderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'nom'                       => $this->nom,
            'organisation'              => $this->organisation,
            'acronyme'                  => $this->acronyme,

            'categorie_id'              => $this->categorie_id,
            'categorie'                 => $this->whenLoaded('categorie', fn () => [
                'id' => $this->categorie->id, 'designation' => $this->categorie->designation,
            ]),
            'role_id'                   => $this->role_id,
            'role'                      => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id, 'designation' => $this->role->designation,
            ] : null),

            'nom_representant'          => $this->nom_representant,
            'fonction'                  => $this->fonction,
            'email'                     => $this->email,
            'telephone'                 => $this->telephone,
            'adresse'                   => $this->adresse,

            'type_contribution_id'      => $this->type_contribution_id,
            'type_contribution'         => $this->whenLoaded('typeContribution', fn () => $this->typeContribution ? [
                'id' => $this->typeContribution->id, 'designation' => $this->typeContribution->designation,
            ] : null),
            'description_contribution'  => $this->description_contribution,
            'montant_estimatif'         => $this->montant_estimatif !== null ? (float) $this->montant_estimatif : null,
            'devise'                    => $this->devise,

            'date_debut'                => $this->date_debut?->toDateString(),
            'date_fin'                  => $this->date_fin?->toDateString(),
            'statut'                    => $this->statut,

            'documents'                 => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id'         => $d->id,
                'libelle'    => $d->libelle,
                'file_name'  => $d->file_name,
                'mime_type'  => $d->mime_type,
                'size'       => $d->size,
                'created_at' => $d->created_at?->toDateTimeString(),
            ])->values()),

            'created_by'                => $this->created_by,
            'created_at'                => $this->created_at?->toDateTimeString(),
            'updated_at'                => $this->updated_at?->toDateTimeString(),
        ];
    }
}

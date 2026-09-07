<?php
// app/Http/Resources/FinancementResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'project_id'                => $this->project_id,
            'type_financement'          => $this->type_financement,
            'statut'                    => $this->statut,
            'mode_contribution'         => $this->mode_contribution,
            'source_financement'        => $this->source_financement,
            'budget_approuve'           => (float) $this->budget_approuve,
            'devise'                    => $this->devise,
            'date_approbation'          => $this->date_approbation?->toDateString(),
            'description'               => $this->description,
            'categorie_contribution_id' => $this->categorie_contribution_id,
            'categorie_contribution'    => $this->whenLoaded('categorieContribution', fn () => $this->categorieContribution ? [
                'id'          => $this->categorieContribution->id,
                'designation' => $this->categorieContribution->designation,
            ] : null),
            'contributions'             => $this->whenLoaded('contributions', fn () => $this->contributions->map(fn ($c) => [
                'id'                        => $c->id,
                'organisme_contributeur_id' => $c->organisme_contributeur_id,
                'organisme_contributeur'    => $c->organismeContributeur ? [
                    'id'          => $c->organismeContributeur->id,
                    'designation' => $c->organismeContributeur->designation,
                ] : null,
                'mode_contribution'         => $c->mode_contribution,
                'montant'                   => (float) $c->montant,
                'devise'                    => $c->devise,
                'date_contribution'         => $c->date_contribution?->toDateString(),
                'categorie_contribution_id' => $c->categorie_contribution_id,
                'categorie_contribution'    => $c->categorieContribution ? [
                    'id'          => $c->categorieContribution->id,
                    'designation' => $c->categorieContribution->designation,
                ] : null,
                'description'               => $c->description,
            ])),
            'project'                   => new ProjectResource($this->whenLoaded('project')),
            'documents'                 => DocumentResource::collection($this->whenLoaded('documents')),
            'created_at'                => $this->created_at?->toISOString(),
        ];
    }
}

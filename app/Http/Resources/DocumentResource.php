<?php
// app/Http/Resources/DocumentResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'titre'            => $this->titre,
            'type'             => $this->type,
            'fichier'          => $this->fichier,
            'fichier_original' => $this->fichier_original,
            'taille'           => $this->taille,
            'mime_type'        => $this->mime_type,
            'description'      => $this->description,
            'project_id'       => $this->project_id,
            'composante_id'    => $this->composante_id,
            'financement_id'   => $this->financement_id,
            'uploaded_by'      => $this->uploaded_by,
            'download_url'     => url("/api/v1/documents/{$this->id}/download"),
            'project'          => new ProjectResource($this->whenLoaded('project')),
            'financement'      => new FinancementResource($this->whenLoaded('financement')),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}

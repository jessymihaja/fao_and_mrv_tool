<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectIdeaFinancement extends Model
{
    protected $fillable = [
        'project_idea_id',
        'organisme_contributeur_id',
        'bailleur_autre',
        'montant_demande',
        'devise',
        'type_financement',
        'statut',
    ];

    protected function casts(): array
    {
        return ['montant_demande' => 'decimal:2'];
    }

    public function projectIdea(): BelongsTo
    {
        return $this->belongsTo(ProjectIdea::class);
    }

    public function organismeContributeur(): BelongsTo
    {
        return $this->belongsTo(OrganismeContributeur::class);
    }

    public function getBailleurLabelAttribute(): string
    {
        return $this->organismeContributeur?->designation ?? $this->bailleur_autre ?? 'Bailleur non précisé';
    }
}

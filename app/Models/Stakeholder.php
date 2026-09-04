<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Partie prenante (Stakeholder).
 *
 * Phase 2 (§M-1 de l'audit) : liaison N:N vers Project et ProjectIdea, via
 * les tables pivot créées par 2026_08_24_080600_create_project_stakeholder_pivots.
 */
class Stakeholder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom', 'organisation', 'acronyme',
        'categorie_id', 'role_id',
        'nom_representant', 'fonction', 'email', 'telephone', 'adresse',
        'type_contribution_id', 'description_contribution', 'montant_estimatif', 'devise',
        'date_debut', 'date_fin', 'statut',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_debut'         => 'date',
            'date_fin'           => 'date',
            'montant_estimatif'  => 'decimal:2',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(StakeholderCategory::class, 'categorie_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(StakeholderRole::class, 'role_id');
    }

    public function typeContribution(): BelongsTo
    {
        return $this->belongsTo(StakeholderContributionType::class, 'type_contribution_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StakeholderDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_stakeholder')
            ->withPivot('role_specifique')->withTimestamps();
    }

    public function projectIdeas(): BelongsToMany
    {
        return $this->belongsToMany(ProjectIdea::class, 'project_idea_stakeholder')
            ->withPivot('role_specifique')->withTimestamps();
    }
}

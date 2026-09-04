<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activite extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'composante_id',
        'code',
        'nom',
        'description',
        'responsable',
        'date_debut',
        'date_fin',
        'budget',
        'devise',
        'statut',
        'pourcentage_avancement',
        'observations',
        'lien',
    ];

    protected function casts(): array
    {
        return [
            'budget'                 => 'decimal:2',
            'date_debut'             => 'date',
            'date_fin'               => 'date',
            'pourcentage_avancement' => 'integer',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function composante(): BelongsTo
    {
        return $this->belongsTo(Composante::class);
    }

    public function indicateurs(): HasMany
    {
        return $this->hasMany(Indicateur::class);
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(ActivitePieceJointe::class);
    }

    // ── Cycle budgétaire (module Budgets) — budgets rattachés uniquement
    // à cette activité ───────────────────────────────────────────────────
    public function budgetPledges(): HasMany
    {
        return $this->hasMany(BudgetPledge::class);
    }

    public function budgetApprobations(): HasMany
    {
        return $this->hasMany(BudgetApprobation::class);
    }

    /** Retourne l'id du projet parent, que l'activité soit rattachée
     *  directement au projet ou via une composante. */
    public function resolveProjectId(): ?int
    {
        return $this->project_id ?? $this->composante?->project_id;
    }
}

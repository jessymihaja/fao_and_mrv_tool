<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Composante extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'code',
        'nom',
        'objectif_specifique',
        'description',
        'responsable',
        'budget',
        'devise',
        'date_debut',
        'date_fin',
        'statut',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'budget'     => 'decimal:2',
            'date_debut' => 'date',
            'date_fin'   => 'date',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class);
    }

    public function indicateurs(): HasMany
    {
        // Indicateurs propres à la composante (hors indicateurs d'activité)
        return $this->hasMany(Indicateur::class)->whereNull('activite_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    // ── Cycle budgétaire (module Budgets) — budgets rattachés uniquement
    // à cette composante, en plus de ceux de son projet/financement ─────────
    public function budgetPledges(): HasMany
    {
        return $this->hasMany(BudgetPledge::class);
    }

    public function budgetApprobations(): HasMany
    {
        return $this->hasMany(BudgetApprobation::class);
    }
}

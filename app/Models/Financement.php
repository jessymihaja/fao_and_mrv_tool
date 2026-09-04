<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Financement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'type_financement',
        'mode_contribution',
        'source_financement',
        'budget_approuve',
        'devise',
        'date_approbation',
        'description',
        'categorie_contribution_id',
    ];

    protected function casts(): array
    {
        return [
            'date_approbation' => 'date',
            'budget_approuve'  => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function categorieContribution(): BelongsTo
    {
        return $this->belongsTo(ContributionCategorie::class, 'categorie_contribution_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(FinancementContribution::class);
    }

    // ── Cycle budgétaire (module Budgets) ───────────────────────────────────
    public function pledges(): HasMany
    {
        return $this->hasMany(BudgetPledge::class);
    }

    public function approbations(): HasMany
    {
        return $this->hasMany(BudgetApprobation::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(Engagement::class);
    }

    public function decaissementPlans(): HasMany
    {
        return $this->hasMany(DecaissementPlan::class);
    }

    public function decaissements(): HasMany
    {
        return $this->hasMany(Decaissement::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }
}
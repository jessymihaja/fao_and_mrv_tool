<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Idée de projet — module indépendant du module Projet, mais relié à lui
 * pour la conversion (voir ProjectIdeaConversionController). Gère le cycle
 * de préparation d'un projet avant son approbation officielle :
 * Brouillon → Soumis → En étude → Approuvé → Converti en Projet.
 */
class ProjectIdea extends Model
{
    use HasFactory, SoftDeletes;

    /** Ordre strict du workflow — utilisé pour valider les transitions de statut. */
    public const WORKFLOW = ['brouillon', 'soumis', 'en_etude', 'approuve', 'converti'];

    protected $fillable = [
        'titre', 'lien', 'acronyme', 'description', 'contexte', 'justification',
        'objectif_general', 'objectifs_specifiques', 'resultats_attendus',
        'duree_prevue_mois', 'date_debut_estimee', 'date_fin_estimee', 'porteur_projet',

        'latitude', 'longitude', 'province_id', 'region_id', 'district_id',
        'commune_id', 'fokontany_id', 'zone_description', 'geo_address',

        'nombre_beneficiaires', 'beneficiaires_hommes', 'beneficiaires_femmes',
        'beneficiaires_jeunes', 'beneficiaires_vulnerables',

        'budget_total_estime', 'devise', 'contribution_nationale',
        'contribution_partenaires', 'cofinancement_prive', 'autres_financements',

        'statut', 'converted_project_id', 'converted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_debut_estimee'        => 'date',
            'date_fin_estimee'          => 'date',
            'converted_at'              => 'datetime',
            'latitude'                  => 'decimal:8',
            'longitude'                 => 'decimal:8',
            'budget_total_estime'       => 'decimal:2',
            'contribution_nationale'    => 'decimal:2',
            'contribution_partenaires'  => 'decimal:2',
            'cofinancement_prive'       => 'decimal:2',
            'autres_financements'       => 'decimal:2',
        ];
    }

    // ── Relations géographiques (mêmes noms que Project) ────────────────────
    public function province(): BelongsTo  { return $this->belongsTo(Province::class); }
    public function region(): BelongsTo    { return $this->belongsTo(Region::class); }
    public function district(): BelongsTo  { return $this->belongsTo(District::class); }
    public function commune(): BelongsTo   { return $this->belongsTo(Commune::class); }
    public function fokontany(): BelongsTo { return $this->belongsTo(Fokontany::class); }

    // ── Onglet 3 : Secteurs ───────────────────────────────────────────────
    public function secteurs(): BelongsToMany
    {
        return $this->belongsToMany(Secteur::class, 'project_idea_secteur');
    }

    // ── Parties prenantes (§M-1 de l'audit) ─────────────────────────────────
    public function stakeholders(): BelongsToMany
    {
        return $this->belongsToMany(Stakeholder::class, 'project_idea_stakeholder')
            ->withPivot('role_specifique')->withTimestamps();
    }

    // ── Onglet 6 : Financements envisagés ────────────────────────────────
    public function financements(): HasMany
    {
        return $this->hasMany(ProjectIdeaFinancement::class);
    }

    // ── Onglet 7 : Documents ─────────────────────────────────────────────
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectIdeaDocument::class);
    }

    // ── Historique du workflow ───────────────────────────────────────────
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProjectIdeaStatusHistory::class)->orderByDesc('created_at');
    }

    // ── Conversion ────────────────────────────────────────────────────────
    public function convertedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'converted_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers métier ────────────────────────────────────────────────────

    /** Somme des 4 sources de contribution (Onglet 5). */
    public function totalContributions(): float
    {
        return (float) $this->contribution_nationale
            + (float) $this->contribution_partenaires
            + (float) $this->cofinancement_prive
            + (float) $this->autres_financements;
    }

    /** Pourcentage de cofinancement = contributions / budget total × 100. */
    public function pourcentageCofinancement(): ?float
    {
        $budget = (float) $this->budget_total_estime;
        if ($budget <= 0) {
            return null;
        }

        return round(($this->totalContributions() / $budget) * 100, 1);
    }

    /**
     * Une transition n'est valide que vers l'étape suivante immédiate du
     * workflow. 'converti' n'est jamais atteignable via cette méthode : il
     * n'est positionné que par ProjectIdeaConversionController.
     */
    public function canTransitionTo(string $nouveauStatut): bool
    {
        $currentIndex = array_search($this->statut, self::WORKFLOW, true);
        $targetIndex  = array_search($nouveauStatut, self::WORKFLOW, true);

        if ($currentIndex === false || $targetIndex === false) {
            return false;
        }
        if ($nouveauStatut === 'converti') {
            return false; // uniquement via la conversion
        }

        return $targetIndex === $currentIndex + 1;
    }
}

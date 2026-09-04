<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Résultat du projet (cadre logique GCF : Impact / Outcome / Output).
 *
 * Volontairement SANS champs valeur_cible / valeur_realisee / unite /
 * date_reference : lorsque le résultat est associé à un indicateur
 * existant (indicateur_id), ces informations sont lues directement sur
 * l'Indicateur — seule source de vérité (voir audit §4). Les accesseurs
 * ci-dessous exposent ces valeurs de façon transparente pour le frontend
 * sans jamais les dupliquer en base.
 */
class Result extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'composante_id',
        'activite_id',
        'indicateur_id',
        'result_type_id',
        'titre',
        'description',
        'reference_year',
        'target_year',
        'statut',
        'valeur_reference',
        'source_verification',
        'methode_collecte',
        'observations',
        'created_by',
    ];

    // Valeurs dérivées de l'indicateur associé, exposées dans le JSON sans
    // jamais être stockées en base (voir accesseurs ci-dessous).
    protected $appends = [
        'valeur_cible', 'valeur_realisee', 'unite', 'annee_realisation', 'pourcentage_atteinte',
    ];

    protected function casts(): array
    {
        return [
            'reference_year'   => 'integer',
            'target_year'      => 'integer',
            'valeur_reference' => 'decimal:4',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function composante(): BelongsTo { return $this->belongsTo(Composante::class); }

    public function activite(): BelongsTo { return $this->belongsTo(Activite::class); }

    public function indicateur(): BelongsTo { return $this->belongsTo(Indicateur::class); }

    // Réutilise le référentiel "result_types" (ajout en ligne possible côté
    // frontend), à la place de l'ancien enum fixe impact/outcome/output.
    public function resultType(): BelongsTo { return $this->belongsTo(ResultType::class); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function piecesJointes(): HasMany { return $this->hasMany(ResultPieceJointe::class); }

    // ── Valeurs dérivées de l'indicateur associé (aucune duplication) ──

    /** Cible : lue sur l'indicateur associé, sinon absente. */
    public function getValeurCibleAttribute(): float|string|null
    {
        return $this->indicateur?->valeur_cible;
    }

    /** Valeur réalisée : lue sur l'indicateur associé, sinon absente. */
    public function getValeurRealiseeAttribute(): float|string|null
    {
        return $this->indicateur?->valeur_realisee;
    }

    /** Unité : lue sur l'indicateur associé, sinon absente. */
    public function getUniteAttribute(): ?string
    {
        return $this->indicateur?->unite;
    }

    /** Année de réalisation : date_reference de l'indicateur associé. */
    public function getAnneeRealisationAttribute(): ?string
    {
        return $this->indicateur?->date_reference?->format('Y-m-d');
    }

    /**
     * Pourcentage d'atteinte — calcul automatique, jamais saisi. Basé sur le
     * taux_atteinte de l'indicateur associé lorsqu'il existe (déjà calculé
     * et maintenu à jour par Indicateur::booted()) ; sinon dérivé de
     * valeur_reference/valeur_cible si les deux sont disponibles.
     */
    public function getPourcentageAtteinteAttribute(): ?float
    {
        if ($this->indicateur) {
            return round((float) $this->indicateur->taux_atteinte, 2);
        }

        return null;
    }
}

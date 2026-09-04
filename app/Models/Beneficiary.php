<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'beneficiary_type_id',
        'beneficiary_category_id',
        'description',
        'region_id',
        'district_id',
        'commune_id',
        'fokontany_id',
        'planned_count',
        'achieved_count',
        'women_count',
        'men_count',
        'youth_count',
        'vulnerable_count',
        'reference_year',
        'monitoring_year',
        'source',
        'observations',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_count'    => 'integer',
            'achieved_count'   => 'integer',
            'women_count'      => 'integer',
            'men_count'        => 'integer',
            'youth_count'      => 'integer',
            'vulnerable_count' => 'integer',
            'reference_year'   => 'integer',
            'monitoring_year'  => 'integer',
            'taux_atteinte'    => 'decimal:4',
        ];
    }

    // ── Calcul automatique du taux d'atteinte avant sauvegarde ──────────
    // Ne jamais permettre la saisie manuelle du pourcentage (cahier des
    // charges §6) : recalculé à chaque sauvegarde à partir de
    // planned_count/achieved_count, comme Indicateur::booted() le fait déjà
    // pour taux_atteinte/niveau_performance.
    protected static function booted(): void
    {
        static::saving(function (Beneficiary $b) {
            $b->taux_atteinte = $b->planned_count > 0
                ? round(($b->achieved_count / $b->planned_count) * 100, 4)
                : 0;
        });
    }

    // ── Relations ─────────────────────────────────────────────────────
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    // Réutilise les référentiels "beneficiary_types"/"beneficiary_categories"
    // (ajout en ligne possible côté frontend), à la place des anciens enums fixes.
    public function beneficiaryType(): BelongsTo { return $this->belongsTo(BeneficiaryType::class); }
    public function beneficiaryCategory(): BelongsTo { return $this->belongsTo(BeneficiaryCategory::class); }

    // Réutilisation des tables géographiques existantes (Project les utilise
    // déjà) — aucune nouvelle structure géographique n'est créée.
    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function district(): BelongsTo { return $this->belongsTo(District::class); }
    public function commune(): BelongsTo { return $this->belongsTo(Commune::class); }
    public function fokontany(): BelongsTo { return $this->belongsTo(Fokontany::class); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}

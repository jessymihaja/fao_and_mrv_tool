<?php
// app/Models/Indicateur.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicateur extends Model
{
    protected $fillable = [
        'project_id', 'composante_id', 'activite_id', 'categorie', 'nom', 'unite',
        'valeur_cible', 'valeur_realisee',
        'taux_atteinte', 'ecart', 'niveau_performance',
        'date_reference', 'commentaire', 'created_by',
    ];

    protected $casts = [
        // decimal:4 (et non 'float') pour éviter toute dérive d'arrondi sur
        // des recalculs répétés (ecart, taux_atteinte) — cohérent avec la
        // précision decimal(15,4)/decimal(8,4) déclarée en base (§É-4 de
        // l'audit).
        'valeur_cible'    => 'decimal:4',
        'valeur_realisee' => 'decimal:4',
        'taux_atteinte'   => 'decimal:4',
        'ecart'           => 'decimal:4',
        'date_reference'  => 'date:Y-m-d',
    ];

    // ── Calcul auto avant sauvegarde ──────────────────────────────
    protected static function booted(): void
    {
        static::saving(function (Indicateur $ind) {
            $ind->ecart = $ind->valeur_realisee - $ind->valeur_cible;

            $ind->taux_atteinte = $ind->valeur_cible > 0
                ? round(($ind->valeur_realisee / $ind->valeur_cible) * 100, 4)
                : 0;

            $ind->niveau_performance = match(true) {
                $ind->taux_atteinte >= 100 => 'Excellent',
                $ind->taux_atteinte >= 75  => 'Bon',
                $ind->taux_atteinte >= 50  => 'Moyen',
                default                    => 'Faible',
            };
        });

        // §É-3 de l'audit : à chaque sauvegarde, la mesure "courante"
        // (valeur_realisee/date_reference) est répercutée dans l'historique
        // indicateur_mesures, sans qu'aucun contrôleur n'ait besoin d'être
        // modifié pour en bénéficier. Un indicateur suivi sur plusieurs
        // années accumule ainsi naturellement sa série temporelle au fil des
        // mises à jour, tout en gardant `valeur_realisee` comme valeur
        // "courante" pour tous les consommateurs existants (Resources,
        // frontend) qui ne lisent pas encore l'historique.
        static::saved(function (Indicateur $ind) {
            if ($ind->date_reference && $ind->valeur_realisee !== null) {
                $ind->mesures()->updateOrCreate(
                    ['date_mesure' => $ind->date_reference->format('Y-m-d')],
                    ['valeur_realisee' => $ind->valeur_realisee, 'created_by' => $ind->created_by]
                );
            }
        });
    }

    // ── Relations ─────────────────────────────────────────────────
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function composante(): BelongsTo
    {
        return $this->belongsTo(Composante::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function justificatifs(): HasMany
    {
        return $this->hasMany(IndicateurJustificatif::class);
    }

    public function mesures(): HasMany
    {
        return $this->hasMany(IndicateurMesure::class)->orderBy('date_mesure');
    }
}

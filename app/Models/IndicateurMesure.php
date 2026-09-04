<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique des mesures d'un indicateur dans le temps (§É-3 de l'audit).
 * Alimenté automatiquement par Indicateur::booted() à chaque sauvegarde —
 * voir le commentaire dans Indicateur.php.
 */
class IndicateurMesure extends Model
{
    protected $fillable = ['indicateur_id', 'valeur_realisee', 'date_mesure', 'commentaire', 'created_by'];

    protected $casts = [
        'valeur_realisee' => 'decimal:4',
        'date_mesure'     => 'date:Y-m-d',
    ];

    public function indicateur(): BelongsTo
    {
        return $this->belongsTo(Indicateur::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

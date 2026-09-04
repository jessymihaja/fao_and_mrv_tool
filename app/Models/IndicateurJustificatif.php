<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicateurJustificatif extends Model
{
    protected $fillable = [
        'indicateur_id', 'fichier', 'nom_original', 'taille', 'mime_type',
    ];

    public function indicateur(): BelongsTo
    {
        return $this->belongsTo(Indicateur::class);
    }
}

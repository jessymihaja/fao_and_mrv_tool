<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapportNational extends Model
{
    protected $table = 'rapports_nationaux';

    protected $fillable = [
        'titre', 'annee', 'region_id',
        'secteur_climatique', 'accredited_entity',
        'source_financement', 'statut_projet',
        'statut', 'contenu', 'created_by',
    ];

    protected $casts = [
        'contenu' => 'array',
        'annee'   => 'integer',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

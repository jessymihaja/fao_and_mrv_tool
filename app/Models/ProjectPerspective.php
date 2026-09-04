<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vision future d'un projet (extension, pérennisation, recherche de
 * nouveau financement...) — alimente la section Homepage "Perspectives des
 * projets" avec des données réellement saisies plutôt que du texte statique.
 */
class ProjectPerspective extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'type_id', 'titre', 'description',
        'zone_extension_envisagee', 'objectif_moyen_terme', 'objectif_long_terme',
        'impact_futur_attendu', 'statut', 'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PerspectiveType::class, 'type_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un sommet du polygone représentant la zone officielle d'intervention
 * d'un projet. Les points sont ordonnés via `ordre` et reliés dans cet
 * ordre pour former le polygone (voir Project::zonePoints()).
 */
class ProjectZonePoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'latitude',
        'longitude',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'float',
            'longitude' => 'float',
            'ordre'     => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

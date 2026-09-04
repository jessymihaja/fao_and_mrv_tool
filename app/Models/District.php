<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class District extends Model
{
    use HasFactory;
    protected $fillable = ['nom', 'code', 'region_id', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }

    /** Projets ayant associé ce district comme zone géographique. */
    public function projectGeographicZones(): MorphMany
    {
        return $this->morphMany(ProjectGeographicZone::class, 'zoneable');
    }
}
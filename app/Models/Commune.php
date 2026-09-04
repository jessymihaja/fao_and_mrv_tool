<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Commune extends Model
{
    use HasFactory;
    protected $fillable = ['nom', 'code', 'district_id', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function fokontany(): HasMany
    {
        return $this->hasMany(Fokontany::class);
    }

    /** Projets ayant associé cette commune comme zone géographique. */
    public function projectGeographicZones(): MorphMany
    {
        return $this->morphMany(ProjectGeographicZone::class, 'zoneable');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une zone géographique (région, district ou commune) associée à un
 * projet. Un même projet peut avoir plusieurs lignes de ce type — voir
 * Project::geographicZones() — affichées ensemble sur une seule carte.
 *
 * `zoneable_type` est un alias court ('region'|'district'|'commune'), pas
 * un nom de classe complet — voir le morphMap enregistré dans
 * AppServiceProvider::boot(). zoneable_id référence regions.id,
 * districts.id ou communes.id selon le type.
 */
class ProjectGeographicZone extends Model
{
    use HasFactory;

    public const TYPE_REGION   = 'region';
    public const TYPE_DISTRICT = 'district';
    public const TYPE_COMMUNE  = 'commune';

    public const TYPES = [self::TYPE_REGION, self::TYPE_DISTRICT, self::TYPE_COMMUNE];

    protected $fillable = [
        'project_id',
        'zoneable_type',
        'zoneable_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Résout vers Region, District ou Commune selon zoneable_type.
     * morphWith() précharge automatiquement la remontée hiérarchique
     * (district->region, commune->district->region) dès que `zoneable`
     * est eager-loadé, pour permettre l'affichage du fil d'ariane
     * (ex: "Moramanga — District — Alaotra-Mangoro") sans requête N+1.
     */
    public function zoneable(): MorphTo
    {
        return $this->morphTo()->morphWith([
            District::class => ['region'],
            Commune::class  => ['district.region'],
        ]);
    }

    /**
     * Représentation unifiée de la zone pour l'API (front : liste, carte,
     * badges). Nécessite que `zoneable` (et sa hiérarchie parente via
     * morphWith) soit déjà chargée — ne déclenche aucune requête
     * supplémentaire (lazy loading désactivé en dev, voir
     * AppServiceProvider).
     */
    public function toDisplayArray(): array
    {
        $zone = $this->zoneable;

        [$regionName, $districtName, $communeName, $lat, $lng] = match ($this->zoneable_type) {
            self::TYPE_REGION   => [$zone->nom, null, null, $zone->latitude, $zone->longitude],
            self::TYPE_DISTRICT => [$zone->region?->nom, $zone->nom, null, $zone->latitude, $zone->longitude],
            self::TYPE_COMMUNE  => [$zone->district?->region?->nom, $zone->district?->nom, $zone->nom, $zone->latitude, $zone->longitude],
            default             => [null, null, null, null, null],
        };

        // Repli sur le centroïde de la région parente si la zone
        // elle-même n'a pas encore de coordonnées renseignées (district
        // ou commune non géocodés) — permet quand même d'afficher un
        // marqueur approximatif plutôt que de masquer la zone.
        if (($lat === null || $lng === null) && $this->zoneable_type !== self::TYPE_REGION) {
            $parentRegion = $this->zoneable_type === self::TYPE_DISTRICT
                ? $zone->region
                : $zone->district?->region;

            $lat ??= $parentRegion?->latitude;
            $lng ??= $parentRegion?->longitude;
        }

        return [
            'id'            => $this->id,
            'zone_type'     => $this->zoneable_type,
            'zone_id'       => $this->zoneable_id,
            'name'          => $zone->nom,
            'region_name'   => $regionName,
            'district_name' => $districtName,
            'commune_name'  => $communeName,
            'latitude'      => $lat !== null ? (float) $lat : null,
            'longitude'     => $lng !== null ? (float) $lng : null,
            'is_approximate_location' => $this->zoneable_type !== self::TYPE_REGION
                && ($zone->latitude === null || $zone->longitude === null),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id_projet',
        'titre',
        // Anciens champs texte conservés pour rétrocompatibilité
        'statut',
        'classification',
        'accredited_entity',
        'secteur_climatique',
        // Nouvelles FK vers les tables de référence
        'status_id',
        'description',
        'date_debut',
        'date_fin',
        'latitude',
        'longitude',
        'province_id',
        'region_id',
        'district_id',
        'commune_id',
        'fokontany_id',
        'zone_description',
        'geo_address',
        'objectifs',
        'impact',
        'problematique_climatique',
        'is_published',
        'project_idea_id',
        'nombre_beneficiaires',
    ];
    // 'wizard_step' est volontairement EXCLU de $fillable : sa progression ne
    // doit être modifiée que via Project::advanceWizardStepTo() (ne peut
    // qu'avancer), jamais via un payload générique store()/update() qui
    // pourrait le faire régresser en contournant la règle métier.

    protected function casts(): array
    {
        return [
            'date_debut'   => 'date',
            'date_fin'     => 'date',
            'latitude'     => 'decimal:8',
            'longitude'    => 'decimal:8',
            'is_published' => 'boolean',
        ];
    }

    // ── Relations géographiques ───────────────────────────────────────────────
    public function province():  BelongsTo { return $this->belongsTo(Province::class); }
    public function region():    BelongsTo { return $this->belongsTo(Region::class); }
    public function district():  BelongsTo { return $this->belongsTo(District::class); }
    public function commune():   BelongsTo { return $this->belongsTo(Commune::class); }
    public function fokontany(): BelongsTo { return $this->belongsTo(Fokontany::class); }

    /**
     * Sommets du polygone de la zone officielle d'intervention du projet,
     * ordonnés — reliés dans cet ordre, ils forment le polygone affiché
     * sur la cartographie (voir GeoController@... / ProjectController).
     */
    public function zonePoints(): HasMany
    {
        return $this->hasMany(ProjectZonePoint::class)->orderBy('ordre');
    }

    /**
     * Zones géographiques multiples (région, district et/ou commune)
     * couvertes par le projet — affichées ensemble sur une seule carte.
     * Distinct de province_id/region_id/district_id/commune_id/fokontany_id
     * (zone unique saisie à la création, conservée pour rétrocompatibilité)
     * et de zonePoints() (polygone dessiné à la main). Voir
     * ProjectGeographicZoneController pour la gestion CRUD.
     */
    public function geographicZones(): HasMany
    {
        return $this->hasMany(ProjectGeographicZone::class)->orderBy('created_at');
    }

    // ── Traçabilité module Idées de projet ─────────────────────────────────────
    public function projectIdea(): BelongsTo { return $this->belongsTo(ProjectIdea::class); }

    // ── Homepage "Perspectives des projets" ─────────────────────────────────
    public function perspectives(): HasMany { return $this->hasMany(ProjectPerspective::class); }

    // ── Parties prenantes (§M-1 de l'audit) ─────────────────────────────────
    public function stakeholders(): BelongsToMany
    {
        return $this->belongsToMany(Stakeholder::class, 'project_stakeholder')
            ->withPivot('role_specifique')->withTimestamps();
    }

    // ── Relations tables de référence ─────────────────────────────────────────
    public function statutRef(): BelongsTo { return $this->belongsTo(Status::class, 'status_id', 'id_status'); }

    public function classifications(): BelongsToMany
    {
        return $this->belongsToMany(
            Classification::class,
            'project_classification',
            'project_id',
            'classification_id',
            'id',
            'id_classification'
        );
    }

    public function entitesAccreditees(): BelongsToMany
    {
        return $this->belongsToMany(
            EntiteAccreditee::class,
            'project_entite_accreditee',
            'project_id',
            'entite_accreditee_id',
            'id',
            'id_entite_accreditee'
        );
    }

    public function domainesIntervention(): BelongsToMany
    {
        return $this->belongsToMany(
            DomaineIntervention::class,
            'project_domaine_intervention',
            'project_id',
            'domaine_intervention_id',
            'id',
            'id_domaine_intervention'
        );
    }

    // ── Relations métier ──────────────────────────────────────────────────────
    public function financements(): HasMany { return $this->hasMany(Financement::class); }
    public function documents():    HasMany { return $this->hasMany(Document::class); }
    public function composantes():  HasMany { return $this->hasMany(Composante::class)->orderBy('ordre'); }
    public function activitesProjet(): HasMany { return $this->hasMany(Activite::class)->whereNull('composante_id'); }
    public function indicateursProjet(): HasMany { return $this->hasMany(Indicateur::class)->whereNull('composante_id')->whereNull('activite_id'); }
    public function documentsProjet():   HasMany { return $this->hasMany(Document::class)->whereNull('composante_id'); }

    // ── Modules Résultats / Bénéficiaires (fiche projet) ──────────────────
    public function results():       HasMany { return $this->hasMany(Result::class); }
    public function beneficiaries(): HasMany { return $this->hasMany(Beneficiary::class); }

    /**
     * Recherche globale, insensible à la casse ET aux accents, sur le
     * titre, l'identifiant lisible (id_projet), le statut, et les 3
     * référentiels multi-valués (classification, entité accréditée,
     * domaine d'intervention / secteur climatique).
     *
     * Seule et unique implémentation de la recherche "projet" côté public
     * (liste ET carte) — voir ProjectController::publicIndex/mapData — pour
     * éviter toute logique de recherche dupliquée ou divergente entre les
     * deux écrans.
     *
     * L'insensibilité aux accents est obtenue via translate() (SQL
     * standard, disponible sans extension), plutôt que via l'extension
     * Postgres "unaccent" qui n'est pas toujours activable selon
     * l'hébergement.
     */
    public function scopeSearchGlobal(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $accented = 'áàâãäåéèêëíìîïóòôõöúùûüçñÁÀÂÃÄÅÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇÑ';
        $plain    = 'aaaaaaeeeeiiiiooooouuuucnAAAAAAEEEEIIIIOOOOOUUUUCN';

        $fold = static fn (string $sqlExpr) => "lower(translate({$sqlExpr}, '{$accented}', '{$plain}'))";

        $needle = '%' . strtr(
            mb_strtolower($term),
            array_combine(mb_str_split($accented), mb_str_split($plain))
        ) . '%';

        return $query->where(function (Builder $q) use ($fold, $needle) {
            $q->whereRaw($fold('titre') . ' like ?', [$needle])
              ->orWhereRaw($fold('id_projet') . ' like ?', [$needle])
              ->orWhereRaw($fold("coalesce(description, '')") . ' like ?', [$needle])
              ->orWhereHas('statutRef', fn ($s) => $s->whereRaw($fold('designation') . ' like ?', [$needle]))
              ->orWhereHas('classifications', fn ($s) => $s->whereRaw($fold('designation') . ' like ?', [$needle]))
              ->orWhereHas('entitesAccreditees', fn ($s) => $s
                  ->whereRaw($fold('designation') . ' like ?', [$needle])
                  ->orWhereRaw($fold("coalesce(sigle, '')") . ' like ?', [$needle]))
              ->orWhereHas('domainesIntervention', fn ($s) => $s->whereRaw($fold('designation') . ' like ?', [$needle]));
        });
    }

    /**
     * Recopie les colonnes texte legacy (statut, secteur_climatique,
     * accredited_entity) à partir des relations FK modernes (status_id,
     * domainesIntervention, entitesAccreditees), qui sont la seule source
     * de vérité saisie par les formulaires actuels.
     *
     * Pourquoi cette méthode existe : RapportNationalController filtre
     * encore les projets sur ces colonnes texte historiques (héritage d'avant
     * la mise en place des tables de référence). Les supprimer casserait ce
     * module de reporting ; les laisser divergentes du statut/des entités
     * réellement sélectionnés par l'utilisateur produirait des rapports
     * nationaux silencieusement faux. Cette méthode élimine cette
     * divergence en s'assurant qu'elles sont TOUJOURS recalculées à partir
     * des données modernes après toute création/modification — ce sont des
     * colonnes dérivées en lecture seule pour le reste de l'application, pas
     * une deuxième source de vérité saisissable.
     *
     * À appeler après que les relations classifications/entitesAccreditees/
     * domainesIntervention aient été synchronisées (sync()), à l'intérieur
     * de la même transaction que la création/modification du projet.
     */
    public function syncLegacyFields(): void
    {
        $this->loadMissing(['statutRef', 'entitesAccreditees', 'domainesIntervention']);

        $this->statut             = $this->statutRef?->designation;
        // Relations multi-valuées : la colonne legacy est mono-valeur, donc
        // on y reflète la première entité liée (comportement pré-existant
        // équivalent : un seul texte libre était saisi à l'époque).
        $this->accredited_entity  = $this->entitesAccreditees->first()?->designation;
        $this->secteur_climatique = $this->domainesIntervention->first()?->designation;

        $this->saveQuietly(); // évite de redéclencher les observers/évènements
    }

    /**
     * Génère un id_projet unique au format GCF-YYYY-XXX. Seule source de
     * vérité pour ce format — utilisée par ProjectController::store() et par
     * ProjectIdeaConversionController lors de la conversion d'une idée en
     * projet.
     *
     * DOIT être appelée à l'intérieur d'une DB::transaction() ouverte par
     * l'appelant : le verrou pessimiste (lockForUpdate) posé ici sur la
     * dernière ligne "GCF-{année}-%" n'est effectif que dans une
     * transaction — il garantit qu'aucune autre requête concurrente ne peut
     * lire/générer le même prochain numéro tant que la transaction
     * englobante n'a pas committé ou fait rollback. Sans transaction
     * englobante, lockForUpdate() n'a aucun effet de verrouillage réel.
     */
    public static function generateIdProjet(): string
    {
        $year   = date('Y');
        $prefix = "GCF-{$year}-";

        $last = self::withTrashed()
            ->where('id_projet', 'like', $prefix . '%')
            ->orderByDesc('id_projet')
            ->lockForUpdate()
            ->value('id_projet');

        $next = $last ? (intval(substr($last, strlen($prefix))) + 1) : 1;

        // Filet de sécurité supplémentaire : même avec le verrou ci-dessus,
        // on revérifie l'unicité candidat par candidat (protège aussi contre
        // les id_projet saisis manuellement qui auraient "sauté" un numéro).
        do {
            $candidate = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (self::withTrashed()->where('id_projet', $candidate)->exists());

        return $candidate;
    }

    /**
     * Fait avancer wizard_step vers $step si $step est strictement supérieur
     * à la progression actuelle (wizard_step ne peut jamais reculer via
     * cette méthode). 'wizard_step' est volontairement absent de $fillable :
     * c'est la seule voie autorisée pour le modifier.
     */
    public function advanceWizardStepTo(int $step): void
    {
        if ($step > $this->wizard_step) {
            $this->wizard_step = $step;
            $this->save();
        }
    }
}

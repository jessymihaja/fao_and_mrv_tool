<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancementContribution extends Model
{
    protected $fillable = [
        'financement_id',
        'composante_id',
        'activite_id',
        'organisme_contributeur_id',
        'mode_contribution',
        'type_mobilisation',
        'montant',
        'devise',
        'date_contribution',
        'categorie_contribution_id',
        'description',
        'commentaire',
        'justificatif_path',
        'justificatif_name',
    ];

    protected function casts(): array
    {
        return [
            'montant'           => 'decimal:2',
            'date_contribution' => 'date',
        ];
    }

    public function financement(): BelongsTo
    {
        return $this->belongsTo(Financement::class);
    }

    public function composante(): BelongsTo
    {
        return $this->belongsTo(Composante::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function organismeContributeur(): BelongsTo
    {
        return $this->belongsTo(OrganismeContributeur::class);
    }

    public function categorieContribution(): BelongsTo
    {
        return $this->belongsTo(ContributionCategorie::class, 'categorie_contribution_id');
    }
}

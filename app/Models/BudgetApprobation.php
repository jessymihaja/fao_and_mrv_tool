<?php
// app/Models/BudgetApprobation.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Étape 4 du cycle budgétaire : "Budgets approuvés" (Approved).
 * Décisions d'approbation officielle par l'organe décisionnel (ex. Conseil
 * du GCF), non encore transférées. Complète le couple
 * (budget_approuve, date_approbation) déjà présent sur Financement en
 * conservant l'historique complet des décisions successives.
 */
class BudgetApprobation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'financement_id',
        'composante_id',
        'activite_id',
        'date_approbation',
        'organisme_id',
        'montant_approuve',
        'devise',
        'reference',
        'decision',
        'justificatif_path',
        'justificatif_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_approbation' => 'date',
            'montant_approuve' => 'decimal:2',
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

    public function organisme(): BelongsTo
    {
        return $this->belongsTo(OrganismeContributeur::class, 'organisme_id');
    }
}

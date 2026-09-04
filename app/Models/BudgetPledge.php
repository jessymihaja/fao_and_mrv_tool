<?php
// app/Models/BudgetPledge.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Étape 1 du cycle budgétaire : "Budgets annoncés / promis" (Pledges).
 * Montants publiquement annoncés par un bailleur, sans engagement juridique.
 */
class BudgetPledge extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'financement_id',
        'composante_id',
        'activite_id',
        'date_annonce',
        'bailleur_id',
        'montant',
        'devise',
        'description',
        'source',
        'justificatif_path',
        'justificatif_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_annonce' => 'date',
            'montant'      => 'decimal:2',
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

    public function bailleur(): BelongsTo
    {
        return $this->belongsTo(OrganismeContributeur::class, 'bailleur_id');
    }
}

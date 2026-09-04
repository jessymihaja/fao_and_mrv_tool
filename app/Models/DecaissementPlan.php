<?php
// app/Models/DecaissementPlan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DecaissementPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'financement_id',
        'composante_id',
        'activite_id',
        'date_prevue',
        'exercice_budgetaire',
        'annee',
        'montant_prevu',
        'devise',
        'statut',
        'description',
        'justificatif_path',
        'justificatif_name',
    ];

    protected function casts(): array
    {
        return [
            'date_prevue'   => 'date',
            'montant_prevu' => 'decimal:2',
            'annee'         => 'integer',
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
}
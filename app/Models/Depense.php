<?php
// app/Models/Depense.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'financement_id',
        'composante_id',
        'activite_id',
        'designation',
        'note',
        'montant',
        'devise',
        'date',
        'annee',
        'semestre',
        'beneficiaire',
        'categorie',
        'reference',
        'justification_path',
        'justification_name',
        'montant_audite',
        'organisme_audit',
        'date_audit',
        'rapport_audit_path',
        'rapport_audit_name',
        'observation_audit',
        'statut',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'date_audit'     => 'date',
            'annee'          => 'integer',
            'montant'        => 'decimal:2',
            'montant_audite' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
<?php
// app/Models/Engagement.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Engagement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'financement_id',
        'composante_id',
        'activite_id',
        'date',
        'reference_accord',
        'bailleur_id',
        'montant',
        'devise',
        'description',
        'justificatif_path',
        'justificatif_name',
    ];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'montant'     => 'decimal:2',
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
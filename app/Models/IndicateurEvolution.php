<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IndicateurEvolution extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_indicateur_evolution';

    protected $fillable = [
        'indicateur_id',
        'annee',
        'montant',
    ];

    protected $casts = [
        'annee'   => 'integer',
        'montant' => 'float',
    ];

    public function indicateur()
    {
        return $this->belongsTo(Indicateur::class, 'indicateur_id', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fokontany extends Model
{
    use HasFactory;

    // La table réelle s'appelle 'fokontany' (invariable) — sans cette
    // déclaration explicite, Eloquent tente par défaut 'fokontanies', qui
    // n'existe pas, et toute requête sur ce modèle échoue (bug pré-existant,
    // touchait déjà GeoController::fokontany() en production).
    protected $table = 'fokontany';

    protected $fillable = ['nom', 'commune_id'];

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}

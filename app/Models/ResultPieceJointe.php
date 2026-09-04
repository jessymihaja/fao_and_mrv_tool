<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultPieceJointe extends Model
{
    // Laravel pluraliserait par défaut le nom de classe en
    // "result_piece_jointes" ; la table créée par la migration est
    // "result_pieces_jointes" (pluriel sur "pieces"). On fixe le nom
    // explicitement pour éviter toute divergence.
    protected $table = 'result_pieces_jointes';

    protected $fillable = [
        'result_id',
        'fichier',
        'nom_original',
        'taille',
        'mime_type',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitePieceJointe extends Model
{
    // Eloquent devinerait "activite_piece_jointes" (il ne pluralise que le
    // dernier mot du nom du modèle) alors que la table réelle, créée par la
    // migration, s'appelle "activite_pieces_jointes". Précision explicite
    // obligatoire pour éviter une erreur "no such table" / "table doesn't exist".
    protected $table = 'activite_pieces_jointes';

    protected $fillable = [
        'activite_id', 'fichier', 'fichier_original', 'taille', 'mime_type', 'uploaded_by',
    ];

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectIdeaStatusHistory extends Model
{
    const UPDATED_AT = null; // journal immuable : uniquement created_at

    // La migration crée la table au singulier ('history', pas 'histories') —
    // sans cette déclaration explicite, Eloquent chercherait par défaut
    // 'project_idea_status_histories' (pluriel anglais de "history").
    protected $table = 'project_idea_status_history';

    protected $fillable = ['project_idea_id', 'ancien_statut', 'nouveau_statut', 'commentaire', 'changed_by'];

    public function projectIdea(): BelongsTo
    {
        return $this->belongsTo(ProjectIdea::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

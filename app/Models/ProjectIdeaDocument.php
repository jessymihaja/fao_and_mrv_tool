<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectIdeaDocument extends Model
{
    protected $fillable = [
        'project_idea_id', 'type', 'libelle',
        'file_path', 'file_name', 'mime_type', 'size', 'uploaded_by',
    ];

    public function projectIdea(): BelongsTo
    {
        return $this->belongsTo(ProjectIdea::class);
    }
}

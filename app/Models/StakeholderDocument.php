<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StakeholderDocument extends Model
{
    protected $fillable = ['stakeholder_id', 'libelle', 'file_path', 'file_name', 'mime_type', 'size', 'uploaded_by'];

    public function stakeholder(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class);
    }
}

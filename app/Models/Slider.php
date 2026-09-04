<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory;
    protected $fillable = ['titre', 'sous_titre', 'image', 'cta_text', 'cta_url', 'ordre', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;
    protected $fillable = ['nom', 'abbr', 'color', 'logo', 'url', 'description', 'ordre', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
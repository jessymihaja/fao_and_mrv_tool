<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['code', 'iso_code', 'designation', 'symbole', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}

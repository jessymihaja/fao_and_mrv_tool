<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicateurReferentiel extends Model
{
    protected $fillable = ['dimension', 'nom', 'unite', 'frequence'];
}

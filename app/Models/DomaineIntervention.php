<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomaineIntervention extends Model
{
        protected $primaryKey = 'id_domaine_intervention';
    protected $fillable = ['designation'];
}

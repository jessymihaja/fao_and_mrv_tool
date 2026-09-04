<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntiteAccreditee extends Model
{
     protected $primaryKey = 'id_entite_accreditee';
    protected $fillable = ['designation', 'sigle'];
}

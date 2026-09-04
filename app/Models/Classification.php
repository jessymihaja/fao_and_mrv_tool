<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classification extends Model
{

    protected $primaryKey = 'id_classification';
    protected $fillable = ['designation'];
}

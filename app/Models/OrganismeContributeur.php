<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganismeContributeur extends Model
{
    protected $table = 'organismes_contributeurs';
    protected $fillable = ['designation'];
}

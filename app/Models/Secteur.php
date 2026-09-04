<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Référentiel "Secteurs" du module Idées de projet (Agriculture, Forêt,
 * Eau, Énergie, Transport, Déchets, Santé, Biodiversité, Adaptation,
 * Atténuation, Autre...). Indépendant des référentiels Classification /
 * DomaineIntervention du module Projet.
 */
class Secteur extends Model
{
    protected $fillable = ['designation'];
}

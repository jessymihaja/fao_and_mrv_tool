<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\Commune;
use App\Models\District;
use App\Models\Region;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel 13: strict model by default in development
        Model::shouldBeStrict(! app()->isProduction());

        // Remove data wrapping from API resources
        JsonResource::withoutWrapping();

        // Force HTTPS in production
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Prevent lazy loading in development
        Model::preventLazyLoading(! app()->isProduction());

        // Prevent silently discarding model attributes
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Alias courts pour la relation polymorphe ProjectGeographicZone::zoneable
        // (module "zones géographiques multiples"). Stocker un alias plutôt
        // que le nom de classe complet dans zoneable_type évite de casser
        // les données existantes si les modèles sont un jour déplacés/renommés.
        Relation::enforceMorphMap([
            'region'   => Region::class,
            'district' => District::class,
            'commune'  => Commune::class,
            'user'     => User::class,
        ]);
    }
}

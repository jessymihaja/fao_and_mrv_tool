<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Point unique de regroupement des montants financiers par devise.
 *
 * Correction du 2026-09 : plusieurs endroits du système faisaient un
 * `->sum('montant')` global sur des enregistrements dont la devise diffère
 * (AR/MGA, USD, EUR), produisant des totaux dénués de sens (ex. 500 USD +
 * 100 AR affiché comme "600"). Cette classe centralise la bonne pratique :
 * ne jamais sommer un champ monétaire sans `GROUP BY devise` (ou son
 * équivalent en collection), et ne jamais fabriquer une entrée à 0 pour une
 * devise qui n'apparaît pas réellement dans les données (ça donnerait
 * l'illusion d'un solde nul au lieu de l'absence de mouvement).
 */
class CurrencyAggregator
{
    /**
     * Regroupe une Collection Eloquent (déjà chargée en mémoire, via ->get()
     * ou un where() sur une collection existante) par devise et somme le
     * champ demandé.
     *
     * @return array<string, float> ex. ['AR' => 100.0, 'USD' => 700.0]
     */
    public static function sumByDevise(Collection $items, string $field, string $deviseField = 'devise'): array
    {
        return $items
            ->filter(fn ($item) => $item->{$deviseField} !== null)
            ->groupBy($deviseField)
            ->map(fn (Collection $group) => round((float) $group->sum($field), 2))
            ->sortKeys()
            ->all();
    }

    /**
     * Équivalent SQL (GROUP BY devise ... SUM(champ)) pour une requête non
     * encore exécutée — à préférer à sumByDevise() quand la volumétrie ne
     * justifie pas de charger toute la collection en mémoire.
     *
     * @param  Builder|Relation  $query
     * @return array<string, float>
     */
    public static function sumByDeviseQuery($query, string $field, string $deviseField = 'devise'): array
    {
        return $query
            ->selectRaw("{$deviseField} as devise, SUM({$field}) as total")
            ->whereNotNull($deviseField)
            ->groupBy($deviseField)
            ->pluck('total', 'devise')
            ->map(fn ($v) => round((float) $v, 2))
            ->sortKeys()
            ->all();
    }

    /**
     * Calcule un taux (numérateur / dénominateur * 100) devise par devise.
     * Une devise présente uniquement au numérateur ou au dénominateur est
     * tout de même incluse dans le résultat, avec un taux à null si le
     * dénominateur correspondant est absent ou nul (jamais de division par
     * une autre devise).
     *
     * @param  array<string, float>  $numerator
     * @param  array<string, float>  $denominator
     * @return array<string, float|null>
     */
    public static function rateByDevise(array $numerator, array $denominator, int $precision = 1): array
    {
        $devises = collect(array_keys($numerator))
            ->merge(array_keys($denominator))
            ->unique()
            ->sort()
            ->values();

        return $devises->mapWithKeys(function (string $devise) use ($numerator, $denominator, $precision) {
            $num = $numerator[$devise] ?? 0.0;
            $den = $denominator[$devise] ?? 0.0;

            return [$devise => $den > 0 ? round(($num / $den) * 100, $precision) : null];
        })->all();
    }

    /**
     * Additionne deux tableaux { devise => montant }, devise par devise
     * (jamais entre devises différentes). Utile pour cumuler plusieurs
     * sous-totaux déjà ventilés par devise (ex. cofinancement public +
     * cofinancement privé + contributions).
     *
     * @param  array<string, float>  ...$breakdowns
     * @return array<string, float>
     */
    public static function merge(array ...$breakdowns): array
    {
        $result = [];
        foreach ($breakdowns as $breakdown) {
            foreach ($breakdown as $devise => $montant) {
                $result[$devise] = round(($result[$devise] ?? 0.0) + $montant, 2);
            }
        }
        ksort($result);

        return $result;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\District;
use App\Models\Fokontany;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json(Province::orderBy('nom')->get());
    }

    public function regions(Request $request, ?int $provinceId = null): JsonResponse
    {
        $regions = Region::orderBy('nom')
            ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
            ->get();

        return response()->json($regions);
    }

    public function districts(Request $request, ?int $regionId = null): JsonResponse
    {
        $districts = District::orderBy('nom')
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->get();

        return response()->json($districts);
    }

    public function communes(Request $request, ?int $districtId = null): JsonResponse
    {
        $communes = Commune::orderBy('nom')
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->get();

        return response()->json($communes);
    }

    public function fokontany(Request $request, ?int $communeId = null): JsonResponse
    {
        $fokontany = Fokontany::orderBy('nom')
            ->when($communeId, fn ($q) => $q->where('commune_id', $communeId))
            ->get();

        return response()->json($fokontany);
    }
}

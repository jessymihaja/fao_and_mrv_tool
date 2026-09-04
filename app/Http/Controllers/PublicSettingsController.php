<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PublicSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = PublicSetting::all()
            ->keyBy('key')
            ->map(fn ($s) => $s->value);

        return response()->json($settings);
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json(PublicSetting::all());
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $request->validate(['value' => ['required']]);

        $setting = PublicSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $request->input('value'),
                'type'  => $request->input('type', 'string'),
            ]
        );

        return response()->json($setting);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Services\ServiceCreateRequest;
use App\Http\Requests\Api\Services\ServiceUpdateRequest;
use App\Http\Requests\Api\Settings\SettingCreateRequest;
use App\Http\Requests\Api\Settings\SettingUpdateRequest;
use App\Models\Service;
use App\Models\ServiceAttribute;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class SettingController extends Controller
{
    public function list(): JsonResponse
    {
        $settings = Setting::all();

        return response()->json($settings);
    }

    public function getSettingsByCategory(\Request $request): JsonResponse
    {
        $settings = Setting::query()->where('category', '=', $request->get('category'))->get();

        return response()->json($settings);
    }

    public function getSettingByName(Request $request): JsonResponse
    {
        $settings = Setting::query()->where('name', '=', $request->name)->first();

        return response()->json($settings);
    }

    public function getSettingById(Setting $setting): JsonResponse
    {

        return response()->json($setting);
    }

    public function store(SettingCreateRequest $request)
    {
        $setting = new Setting();
        $setting->fill($request->only(['name','value', 'category']));

        return response()->json([
            'success' => $setting->save(),
            'data' => $setting
        ]);
    }

    public function update(Setting $setting, SettingUpdateRequest $request): JsonResponse
    {

        $setting->category = $request->get('category');
        $setting->name = $request->get('name');
        $setting->value = $request->get('value');
        return response()->json([
            'success' => $setting->save(),
            'data' => $setting
        ]);
    }

    public function destroy(Setting $setting): JsonResponse
    {
        return response()->json([
            'success' => $setting->delete()
        ]);
    }

}

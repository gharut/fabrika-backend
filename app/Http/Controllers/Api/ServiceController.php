<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Services\ServiceCreateRequest;
use App\Http\Requests\Api\Services\ServiceUpdateRequest;
use App\Models\Service;
use App\Models\ServiceAttribute;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;


class ServiceController extends Controller
{

    public function get(Tag $tag): JsonResponse
    {
        $tag->load(['consumables','suppliers']);
        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    public function list(): JsonResponse
    {
        $services = Service::query()
            ->with('tags', 'attributes', 'creator', 'updater')
            ->orderBy('sort')
            ->get();

        return response()->json($services);
    }

    public function listWithCounts(): JsonResponse
    {
        $tags = Tag::query()->withCount(['consumables', 'suppliers'])->get();
        return response()->json($tags);
    }

    public function listSuppliers(Tag $tag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tag->load("suppliers")
        ]);
    }

    public function listConsumables(Tag $tag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tag->load("consumables")
        ]);
    }

    public function store(ServiceCreateRequest $request)
    {

        $service = new Service();
        $service->fill($request->only(['name', 'use_consumable', 'step', 'apply_to', 'multiple_products', 'count_label', 'report_type', 'price','sort']));
        $service->created_by = Auth::id();
        $service->updated_by = Auth::id();
        $saved = $service->save();
        if($saved) {
            if($request->get('attributes')) {
                foreach ($request->get('attributes') as $attribute) {
                    $serviceAttribute = new ServiceAttribute();
                    $serviceAttribute->service_id = $service->id;
                    $serviceAttribute->fill($attribute);
                    $serviceAttribute->save();
                }
            }

            if($request->get('tags')) {
                $service->tags()->attach($request->get('tags'));
            }
        }

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $service->load('tags', 'attributes', 'creator', 'updater') : [],
        ]);
    }

    public function update(ServiceUpdateRequest $request, Service $service) {
        $service->fill($request->only(['name', 'use_consumable', 'step', 'apply_to', 'multiple_products', 'count_label', 'report_type', 'price', 'sort']));
        $service->updated_by = Auth::id();
        $saved = $service->save();
        if($saved) {
            $service->attributes()->delete();
            if($request->get('attributes')) {
                foreach ($request->get('attributes') as $attribute) {
                    $serviceAttribute = new ServiceAttribute();
                    $serviceAttribute->service_id = $service->id;
                    $serviceAttribute->fill($attribute);
                    $serviceAttribute->save();
                }
            }
            $service->tags()->detach();
            if($request->get('tags')) {
                $service->tags()->attach($request->get('tags'));
            }
        }

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $service->load('tags', 'attributes', 'creator', 'updater') : [],
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->tags()->detach();
        $service->attributes()->delete();

        return response()->json([
            'success' => $service->delete()
        ]);
    }

}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Supplier\SupplierCreateRequest;
use App\Http\Requests\Api\Supplier\SupplierUpdateRequest;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function list(): JsonResponse
    {
        $suppliers = Supplier::all()->load('tags:id,name') ;
        return response()->json($suppliers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierCreateRequest $request): JsonResponse
    {

        $supplier = new Supplier();


        $supplier->fill($request->only("name", "address", "website", "contacts", "payments"));

        $saved = $supplier->save();

        if ($saved) {
            $tags = $request->get("tags");
            if ($tags) {
                $supplier->tags()->attach($tags);
            }

            $supplier->load('tags:id,name');
        }

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $supplier : [],
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function get(Supplier $supplier): JsonResponse
    {

        $supplier->load('tags:id,name');
        return response()->json([
            'success' => true,
            'data' => $supplier,
        ]);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->fill($request->only("name", "address", "website", "contacts", "payments"));
        $saved = $supplier->save();
        $supplier->tags()->detach();

        $tags = $request->get("tags");
        if ($tags) {
            $supplier->tags()->attach($request->get('tags'));
        }

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $supplier->load('tags:id,name') : []
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->tags()->detach();
        $supplier->delete();

        return response()->json([
            'success' => $supplier->delete(),
            'data' => null
        ]);
    }
}

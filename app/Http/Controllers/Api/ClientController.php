<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\ClientCreateRequest;
use App\Models\Client;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
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
        $clients = Client::all();
        return response()->json($clients);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientCreateRequest $request): JsonResponse
    {
        $client = new Client();

        $client->fill($request->only(
            'name',
            'type',
            'email',
            'phone',
            'telegram',
            'details',
            'tin',
            'psrn',
            'account',
            'bank',
            'correspondent_account',
            'bic',
            'legal_address',
            'vat'
        ));

        $saved = $client->save();

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $client : [],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function get(Client $client): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $client,
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
    public function destroy(Client $client): JsonResponse
    {
        return response()->json([
            'success' => $client->delete(),
            'data' => null
        ]);
    }
}

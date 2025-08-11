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

    public function getAllFiltered(Request $request): JsonResponse
    {
        $filters = $request->input('filters', []);
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'asc');

        $query = Client::query();

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $op = $filter['op'] ?? 'eq';
            $value = $filter['value'] ?? null;

            if (!$field || $value === null) continue;

            switch ($op) {
                case 'eq':
                    $query->where($field, '=', $value);
                    break;
                case 'ne':
                    $query->where($field, '!=', $value);
                    break;
                case 'like':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;
            }
        }

        $sortDir = in_array(strtolower($sortDir), ['asc', 'desc']) ? $sortDir : 'asc';
        if ($sortBy === 'type') {
            $query->orderByRaw("
                CASE type
                    WHEN 'individual' THEN 'Индивидуальный предприниматель'
                    WHEN 'legal_entity' THEN 'Юридическое лицо'
                    ELSE type
                END $sortDir
            ");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return response()->json(
            $query->get()
        );
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
            'vat',
            'wb_api_token'
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
    public function update(Request $request, Client $client): JsonResponse
    {
        $client->fill($request->only('name',
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
            'vat',
            'wb_api_token'
        ));

        $saved = $client->save();

        return response()->json([
            'success' => $saved,
            'data' => $client
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

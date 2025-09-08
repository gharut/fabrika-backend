<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ClientUser\ClientUserCreateRequest;
use App\Http\Requests\Api\ClientUser\ClientUserUpdateRequest;
use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use App\Services\ClientUserService;
use Illuminate\Http\JsonResponse;

class ClientUserController extends Controller
{
    public function __construct(
        protected ClientUserService $service
    ) {}

    public function index(): JsonResponse
    {
        $items = ClientUser::with(['client', 'user', ])->get();
        return response()->json($items);
    }

    public function store(ClientUserCreateRequest $request): JsonResponse
    {
        $item = $this->service->create($request->validated());
        return response()->json($item, 201);
    }

    public function show($id): JsonResponse
    {
        $clientUser = ClientUser::with(['client', 'user', 'role'])->find($id);        
        return response()->json($clientUser);
    }

    public function update(ClientUserUpdateRequest $request, $id): JsonResponse
    {
        $clientUser = ClientUser::find($id);
        
        if (!$clientUser) {
            return response()->json(['error' => 'Client user relation not found'], 404);
        }
        
        $item = $this->service->update($clientUser, $request->validated());
        return response()->json($item);
    }

    public function destroy($id): JsonResponse
    {
        $clientUser = ClientUser::find($id);
        
        if (!$clientUser) {
            return response()->json(['error' => 'Client user relation not found'], 404);
        }
        
        $this->service->delete($clientUser);
        return response()->json(null, 204);
    }
}

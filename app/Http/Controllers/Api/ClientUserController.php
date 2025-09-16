<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ClientUser\ClientUserCreateRequest;
use App\Http\Requests\Api\ClientUser\ClientUserUpdateRequest;
use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use Illuminate\Http\Request;
use App\Services\ClientUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
        $clientUser = ClientUser::with(['client', 'user'])->find($id);
        if ($clientUser) {
            $clientUser->user->load('roles');
        }
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

    public function destroy(Request $request, $userId): JsonResponse
    {
        $clientId = $request->header('X-Client-Id');
        $clientUser = ClientUser::where('client_id', $clientId)
            ->where('user_id', $userId)
            ->first();
        
        if (!$clientUser) {
            return response()->json([
                'error' => 'Связь пользователя с клиентом не найдена'
            ], 404);
        }
        
        $this->service->delete($clientUser);
        return response()->json(['success' => true], 200);
    }

    public function getClientsByUser()
    {
        $userId = Auth::id();
        $users = $this->service->getClientsByUser($userId);
        return response()->json($users, 200);
    }

    public function getUsersByClient(Request $request)
    {
        $clientId = $request->header('X-Client-Id');
        if (!$clientId) {
          return response()->json([], 200);  
        }

        $clients = $this->service->getUsersByClient($clientId);
        return response()->json($clients, 200);
    }
}

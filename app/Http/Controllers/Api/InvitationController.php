<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Invitation\InvitationAcceptRequest;
use App\Http\Requests\Api\Invitation\InvitationStoreRequest;
use App\Models\Invitation;
use App\Http\Controllers\Controller;
use App\Services\InvitationService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $service) {}

    // Админ создаёт приглашение
    public function store(InvitationStoreRequest $request)
    {
        $user = $request->user();
        $clientId = $user->current_client_id ?? $request->header('X-Client-Id');

        $inv = $this->service->create(
            clientId: (int)$clientId,
            inviterId: (int)$user->id,
            email: $request->string('email'),
            roleId: (int)$request->integer('role_id'),
            meta: $request->input('meta')
        );

        // Возвращаем данные приглашения с preview письма
        return response()->json($inv, 201);
    }

    // Список активных/всех приглашений текущего клиента
    public function index(Request $request)
    {
        $clientId = $request->user()->current_client_id ?? $request->header('X-Client-Id');

        $query = Invitation::where('client_id', $clientId)->latest();
        if ($request->boolean('active_only', true)) {
            $query->whereNull('accepted_at')->whereNull('revoked_at')
                  ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at','>',now()); });
        }

        return $query->paginate(20);
    }

    public function revoke(int $id)
    {
        $this->service->revoke($id);
        return response()->json(['status'=>'ok']);
    }

    public function accept(InvitationAcceptRequest $request)
    {
        $token = $request->string('token'); 
        $result = $this->service->accept($token);

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? ''
        ]);
    }
}
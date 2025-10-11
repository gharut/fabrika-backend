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

    public function index(Request $request)
    {
        $user = $request->user();
        $currentUserId = $user->id;
        $currentUserEmail = $user->email;
        $clientId = $request->header('X-Client-Id');

        $invitations = Invitation::join('clients', 'invitations.client_id', '=', 'clients.id')
            ->join('users', 'invitations.inviter_id', '=', 'users.id')
            ->join('roles', 'invitations.role_id', '=', 'roles.id')
            ->select([
                'invitations.id',
                'invitations.email',
                'invitations.created_at',
                'clients.name as client_name',
                'clients.id as client_id',
                'users.name as inviter_name',
                'users.id as inviter_id',
                'roles.name as role_name',
                'roles.id as role_id'
            ])
            ->where('invitations.inviter_id', $currentUserId)
            ->orWhere('invitations.email', $currentUserEmail)
            ->orWhere('invitations.client_id', $clientId)
            ->latest('invitations.created_at')
            ->get()
            ->map(function($invitation) {
                return [
                    'id' => $invitation->id,
                    'client' => [
                        'id' => $invitation->client_id,
                        'name' => $invitation->client_name
                    ],
                    'inviter' => [
                        'id' => $invitation->inviter_id,
                        'name' => $invitation->inviter_name
                    ],
                    'role' => [
                        'id' => $invitation->role_id,
                        'name' => $invitation->role_name
                    ],
                    'email' => $invitation->email,
                    'created_at' => $invitation->created_at
                ];
            });

        return $invitations;
    }

    public function revoke(int $id)
    {
        $this->service->revoke($id);
        return response()->json([
            'success' => true,
            'message' => ''
        ]);
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
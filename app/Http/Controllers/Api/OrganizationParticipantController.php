<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrganizationParticipant\OrganizationParticipantCreateRequest;
use App\Http\Requests\Api\OrganizationParticipant\OrganizationParticipantUpdateRequest;
use App\Services\OrganizationParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationParticipantController extends Controller
{
    public function __construct(
        protected OrganizationParticipantService $service
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->listAll());
    }

    public function store(OrganizationParticipantCreateRequest $request): JsonResponse
    {
        try {
            $participant = $this->service->create($request->validated());

            return response()->json($participant, 201);

        } catch (\InvalidArgumentException $e) {
            $code = $e->getCode() === 409 ? 409 : 422;
            return response()->json(['success'=> false, 'message'=>$e->getMessage()], $code);
        } catch (\Throwable $e) {
            Log::error('Ошибка создания участника организации', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка создания участника организации. Обратитесь к администратору.',
            ], 500);
        }
    }

    public function addFulfillmentToClient(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['fulfillment_id' => 'required']);
            $organizationId = (int) $request->header('X-Client-Id');
            $result = $this->service->addFulfillmentToClient($organizationId, (int) $validated['fulfillment_id']);
            
            if ($result['success']) {
                return response()->json($result, 201);
            }

            return response()->json($result, $result['code']);
        } catch (\Exception $e) {
            Log::error('Ошибка при добавлении фулфилмента.', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при добавлении фулфилмента.'
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->service->show($id);
        return $item
            ? response()->json($item)
            : response()->json(['message' => 'Участник не найден'], 404);
    }

    public function update(OrganizationParticipantUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $organizationParticipant = $this->service->updateParticipantRole($id, $request->role_id);

            if ($organizationParticipant === null) {
                return response()->json([
                    'message' => 'Связь участника с организацией не найдена',
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            if ($organizationParticipant === false) {
                return response()->json([
                    'message' => 'Обновление доступно только для участников-пользователей',
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            return response()->json($organizationParticipant);

        } catch (\Throwable $e) {
            Log::error('Неожиданная ошибка при обновлении участника организации', [
                'participant_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Произошла внутренняя ошибка при обновлении участника',
                'error' => $e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->service->delete($id);
            if ($result['success']) {
                return response()->json(['success' => true], 200, [], JSON_UNESCAPED_UNICODE);
            }
            return response()->json([
                    'success' => $result['success'],
                    'message' => $result['message'],
                ], 
                $result['code'], 
                [], JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            Log::error('Ошибка удаления участника организации', [
                'participant_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Не удалось удалить участника организации',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function getClientsByUser()
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json([
                'error' => 'Не удалось определить пользователя',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $clients = $this->service->getClientsByUser($userId);
            return response()->json($clients, 200);
        } catch (\Throwable $e) {
            Log::error('Ошибка при получении списка оргназаций', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Не удалось получить список организаций',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }

    }

    public function getUsersByClient(Request $request)
    {
        $clientId = $request->header('X-Client-Id')
            ?? $request->route('client_id')
            ?? $request->input('client_id');

        if (! $clientId) {
            return response()->json(['message' => 'Client id is required'], 422);
        }

        try {
            $users = $this->service->getUsersByClient($clientId);
            return response()->json($users, 200);
        } catch (\Throwable $e) {
            Log::error('Ошибка при получении списка участников', [
                'client_id' => $clientId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Не удалось получить список участников',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function getInvitationsByClient(Request $request)
    {
        $clientId = $request->header('X-Client-Id') ?? $request->route('client_id') ?? $request->input('client_id');

        $user = $request->user();
        $invitations = $this->service->getInvitations($user, $clientId);
        return response()->json($invitations->sortByDesc('created_at')->values());
    }

    public function updateRole(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (! $currentUser->isSystemUser()) {
            return response()->json([
                'message' => 'Forbidden',
                'permission' => ['code' => '',  'visible_name' => ''],
            ], 403);
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'client_id' => 'required|exists:clients,id',
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $organizationParticipant = $this->service->updateUserRole(
                organizationId: $request->client_id,
                userId: $request->user_id,
                roleId: $request->role_id
            );

            return response()->json([
                'message' => 'Роль пользователя успешно обновлена',
                'client_user' => $organizationParticipant,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при обновлении роли',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

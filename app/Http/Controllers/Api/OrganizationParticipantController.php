<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrganizationParticipant\OrganizationParticipantCreateRequest;
use App\Http\Requests\Api\OrganizationParticipant\OrganizationParticipantUpdateRequest;
use App\Models\Client;
use App\Models\OrganizationParticipant;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class OrganizationParticipantController extends Controller
{
    public function __construct(
        protected OrganizationParticipantService $service
    ) {}

    public function index(): JsonResponse
    {
        $items = OrganizationParticipant::with(['client', 'user'])->get();

        return response()->json($items);
    }

    public function store(OrganizationParticipantCreateRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['client_id']) && isset($data['user_id'])) {
            $payload = [
                'organization_id' => $data['client_id'],
                'model_type' => 'user',
                'model_id' => $data['user_id'],
            ];
        } elseif (isset($data['organization_id']) && isset($data['model_type']) && isset($data['model_id'])) {
            $payload = [
                'organization_id' => $data['organization_id'],
                'model_type' => $data['model_type'],
                'model_id' => $data['model_id'],
            ];
        } else {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $item = OrganizationParticipant::create($payload);

        if ($item->model_type === 'user' || $item->model_type === \App\Models\User::class) {
            $item->load('user.roles');
        }

        $item->load('client');

        return response()->json($item, 201);
    }

    public function addFulfillmentToClient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fulfillment_id' => 'required',
        ]);

        $organizationId = $request->header('X-Client-Id');
        $fulfillmentId = (int) $validated['fulfillment_id'];

        $organization = \App\Models\Client::withoutGlobalScopes()
            ->find($organizationId);

        if (! $organization || ! $organizationId) {
            return response()->json([
                'message' => 'Организация с таким ID не найдена.',
            ], 404);
        }

        $fulfillment = \App\Models\Client::withoutGlobalScopes()
            ->find($fulfillmentId);

        if (! $fulfillment) {
            return response()->json([
                'message' => 'Фулфилмент с таким ID не найдена.',
            ], 404);
        }

        if (! $fulfillment->is_fulfillment) {
            return response()->json([
                'message' => 'Указанная организация не является фулфилментом.',
            ], 422);
        }

        $exists = \App\Models\OrganizationParticipant::where([
            'organization_id' => $organizationId,
            'model_type' => \App\Models\Client::class,
            'model_id' => $fulfillmentId,
        ])->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Фулфилмент уже добавлен.',
            ], 409);
        }

        $link = \App\Models\OrganizationParticipant::create([
            'organization_id' => $organizationId,
            'model_type' => \App\Models\Client::class,
            'model_id' => $fulfillmentId,
        ]);

        $fulfillmentUsers = DB::table('organization_participants as op')
            ->leftJoin('model_has_roles as mhr', function ($join) use ($fulfillmentId) {
                $join->on('mhr.model_id', '=', 'op.model_id')
                    ->where('mhr.client_id', '=', $fulfillmentId)
                    ->where('mhr.model_type', '=', \App\Models\User::class);
            })
            ->where('op.organization_id', $fulfillmentId)
            ->where('op.model_type', \App\Models\User::class)
            ->select([
                'op.model_id as user_id',
                'mhr.role_id as role_id',
            ])
            ->get();

        foreach ($fulfillmentUsers as $item) {
            $user = User::find($item->user_id);
            if (! $user) {
                continue;
            }

            $alreadyExists = \App\Models\OrganizationParticipant::where([
                'organization_id' => $organizationId,
                'model_type' => \App\Models\User::class,
                'model_id' => $item->user_id,
            ])->exists();

            if ($alreadyExists) {
                continue;
            }

            if ($user->roles()->exists()) {
                continue;
            }

            $mappedRoleId = $this->mapFulfillmentRoleId($item->role_id);
            $role = Role::find($mappedRoleId);

            if (! $role) {
                Log::warning('Роль по mappedRoleId не найдена. Пользователь не привязан', [
                    'mappedRoleId' => $mappedRoleId,
                    'user_id' => $item->user_id,
                ]);

                continue;
            }

            $user->assignRole($role);
        }

        $link->load('client', 'model');

        return response()->json([
            'message' => 'Фулфилмент успешно добавлен.',
            'data' => $link,
        ], 201);
    }

    private function mapFulfillmentRoleId(int $roleId): int
    {
        $map = [
            1 => 5,
            2 => 6,
            3 => 7,
        ];

        return $map[$roleId] ?? $roleId;
    }

    public function show($id): JsonResponse
    {
        $organizationParticipant = OrganizationParticipant::with(['client', 'user'])->find($id);

        if ($organizationParticipant && ($organizationParticipant->model_type === 'user' || $organizationParticipant->model_type === \App\Models\User::class)) {
            $organizationParticipant->user->load('roles');
        }

        return response()->json($organizationParticipant);
    }

    public function update(OrganizationParticipantUpdateRequest $request, $id): JsonResponse
    {
        try {
            $organizationParticipant = OrganizationParticipant::find($id);

            if (! $organizationParticipant) {
                return response()->json([
                    'message' => 'Связь участника с организацией не найдена',
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            if ($organizationParticipant->model_type !== \App\Models\User::class) {
                return response()->json([
                    'message' => 'Обновление доступно только для участников-пользователей',
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            $roleId = $request->role_id;
            $organizationParticipant = $this->updateUserRole(
                clientId: $organizationParticipant->organization_id,
                userId: $organizationParticipant->model_id,
                roleId: $roleId
            );

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
        $participant = OrganizationParticipant::find($id);

        if (! $participant) {
            return response()->json([
                'error' => 'Связь участника с организацией не найдена',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $this->service->delete($participant);

            return response()->json(['success' => true], 200, [], JSON_UNESCAPED_UNICODE);
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

        $direct = DB::table('organization_participants as op')
            ->join('clients as org', 'org.id', '=', 'op.organization_id')
            ->where(function ($q) use ($userId) {
                $q->where('op.model_type', 'user')->where('op.model_id', $userId)
                    ->orWhere(function ($qq) use ($userId) {
                        $qq->where('op.model_type', \App\Models\User::class)
                            ->where('op.model_id', $userId);
                    });
            })
            ->select('org.id', 'org.name');

        $viaFulfillment = DB::table('organization_participants as staff')
            ->join('clients as f', 'f.id', '=', 'staff.organization_id')
            ->join('organization_participants as link', function ($j) {
                $j->on('link.model_id', '=', 'f.id')
                    ->where('link.model_type', \App\Models\Client::class);
            })
            ->join('clients as client', 'client.id', '=', 'link.organization_id')
            ->where('f.is_fulfillment', 1)
            ->where(function ($q) use ($userId) {
                $q->where('staff.model_type', 'user')->where('staff.model_id', $userId)
                    ->orWhere(function ($qq) use ($userId) {
                        $qq->where('staff.model_type', \App\Models\User::class)
                            ->where('staff.model_id', $userId);
                    });
            })
            ->select('client.id', 'client.name');

        $clients = $direct->union($viaFulfillment)->get();

        return response()->json($clients, 200);
    }

    public function getUsersByClient(Request $request)
    {
        $clientId = $request->header('X-Client-Id')
            ?? $request->route('client_id')
            ?? $request->input('client_id');

        if (! $clientId) {
            return response()->json(['message' => 'Client id is required'], 422);
        }

        $directUsers = DB::table('organization_participants as op')
            ->join('users as u', 'u.id', '=', 'op.model_id')
            ->where('op.organization_id', $clientId)
            ->where(function ($q) {
                $q->where('op.model_type', 'user')
                    ->orWhere('op.model_type', \App\Models\User::class);
            })
            ->selectRaw("
                op.id as id,
                u.id as user_id,
                u.name,
                u.email,
                u.phone,
                u.address,
                u.avatar,
                u.created_at,
                'user' as _type
            ");

        $directFulfillments = DB::table('organization_participants as op')
            ->join('clients as f', 'f.id', '=', 'op.model_id')
            ->where('op.organization_id', $clientId)
            ->where('op.model_type', \App\Models\Client::class)
            ->where('f.is_fulfillment', 1)
            ->selectRaw("
                op.id as id,
                f.id as user_id,
                f.name,
                f.email,
                f.phone,
                f.legal_address as address,
                NULL as avatar,
                f.created_at,
                'fulfillment' as _type
            ");

        $union = $directUsers->unionAll($directFulfillments);

        $items = DB::query()
            ->fromSub($union, 't')
            ->orderByDesc('created_at')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);

        $userIds = $items->where('_type', 'user')->pluck('user_id')->unique()->all();
        $usersMap = User::with('roles')->whereIn('id', $userIds)->get()->keyBy('id');

        $fulfillmentRole = Role::where('name', 'fulfillment')->first();

        $result = $items->map(function ($row) use ($usersMap, $fulfillmentRole) {
            if ($row->_type === 'user') {
                $u = $usersMap->get($row->user_id);
                $role = $u?->roles?->first();

                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'address' => $row->address,
                    'avatar' => $row->avatar,
                    'created_at' => $row->created_at,
                    'role' => $role ? [
                        'id' => $role->id,
                        'name' => $role->name,
                        'visible_name' => $role->visible_name ?? $role->name,
                    ] : null,
                ];
            }

            $rolePayload = $fulfillmentRole
                ? [
                    'id' => $fulfillmentRole->id,
                    'name' => $fulfillmentRole->name,
                    'visible_name' => $fulfillmentRole->visible_name ?? $fulfillmentRole->name,
                ]
                : [
                    'id' => null,
                    'name' => 'fulfillment',
                    'visible_name' => 'Фулфилмент',
                ];

            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'phone' => $row->phone,
                'address' => $row->address,
                'avatar' => $row->avatar,
                'created_at' => $row->created_at,
                'role' => $rolePayload,
            ];
        })->values();

        return response()->json($result);
    }

    public function getInvitationsByClient(Request $request)
    {
        $clientId = $request->header('X-Client-Id') ?? $request->route('client_id') ?? $request->input('client_id');

        // Если у тебя есть метод $this->service->getInvitations($user, $clientId) — вызываем его:
        $user = $request->user();
        if (method_exists($this->service, 'getInvitations')) {
            $invitations = $this->service->getInvitations($user, $clientId);

            return response()->json($invitations->sortByDesc('created_at')->values());
        }

        return response()->json([], 200);
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
            'client_id' => 'required|exists:organizations,id',
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $organizationParticipant = $this->updateUserRole(
                clientId: $request->client_id,
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

    private function updateUserRole(int $clientId, int $userId, int $roleId): OrganizationParticipant
    {
        $organizationParticipant = OrganizationParticipant::where('organization_id', $clientId)
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', $userId)
            ->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);

        $role = Role::findOrFail($roleId);

        $user = \App\Models\User::findOrFail($userId);
        $user->syncRoles([$role->name]);

        $organizationParticipant->load('user', 'client');

        return $organizationParticipant;
    }
}

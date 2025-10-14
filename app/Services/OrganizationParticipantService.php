<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\OrganizationParticipant;
use App\Models\Role;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class OrganizationParticipantService
{
    public function listAll()
    {
        return OrganizationParticipant::with(['client', 'user'])->get();
    }

    public function create(array $data): OrganizationParticipant
    {
        $organizationId = (int) $data['organization_id'];
        $modelId        = (int) $data['model_id'];
        $modelType      = $this->normalizeModelType($data['model_type']);
        $roleId         = (int) $data['role_id'];

        if (! $organizationId || ! $modelId || ! $modelType) {
            throw new \InvalidArgumentException('Неверные данные');
        }

        $exists = OrganizationParticipant::where([
            'organization_id' => $organizationId,
            'model_type'      => $modelType,
            'model_id'        => $modelId,
        ])->exists();

        if ($exists) {
            throw new \InvalidArgumentException('Участник уже связан с организацией', 409);
        }

        return DB::transaction(function () use ($organizationId, $modelType, $modelId, $roleId) {
            $participant = OrganizationParticipant::create([
                'organization_id' => $organizationId,
                'model_type'      => $modelType,
                'model_id'        => $modelId,
            ]);

            if ($modelType === User::class) {
                $this->updateUserRole($organizationId, $modelId, $roleId);
                $participant->load(['user.roles', 'client']);

                $org = Client::withoutGlobalScopes()->find($organizationId);
                if ($org && $org->is_fulfillment) {
                    $this->propagateUserFromFulfillmentToParents($organizationId, $modelId);
                }
            } else {
                $participant->load(['client']);
            }

            return $participant;
        });
    }

    public function addFulfillmentToClient(int $organizationId, int $fulfillmentId)
    {
        $organization = \App\Models\Client::withoutGlobalScopes()
            ->find($organizationId);

        if (! $organization || ! $organizationId) {
            return [
                'success' => false,
                'message' => 'Организация с таким ID не найдена.',
                'code' => 404,
            ];
        }

        $fulfillment = \App\Models\Client::withoutGlobalScopes()
            ->find($fulfillmentId);

        if (! $fulfillment) {
            return [
                'success' => false,
                'message' => 'Фулфилмент с таким ID не найдена.',
                'code' => 404,
            ];
        }

        if (! $fulfillment->is_fulfillment) {
            return [
                'success' => false,
                'message' => 'Указанная организация не является фулфилментом.',
                'code' => 422,
            ];
        }

        $exists = \App\Models\OrganizationParticipant::where([
            'organization_id' => $organizationId,
            'model_type' => \App\Models\Client::class,
            'model_id' => $fulfillmentId,
        ])->exists();

        if ($exists) {
            return [
                'success' => false,
                'message' => 'Фулфилмент уже добавлен.',
                'code' => 409,
            ];
        }

        return \DB::transaction(function () use ($organizationId, $fulfillmentId) {
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

                app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);
                if ($this->userHasAnyRoleInOrg($organizationId, (int)$item->user_id)) {
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

                $user->assignRole($role->name);
            }

            $link->load('client', 'model');

            return [
                'success' => true,
                'message' => 'Фулфилмент успешно добавлен.',
                'data' => $link,
            ];
        });
    }

    public function show($id)
    {
        $organizationParticipant = OrganizationParticipant::with(['client', 'user'])->find($id);

        if ($organizationParticipant && $organizationParticipant->model_type === \App\Models\User::class) {
            $organizationParticipant->user->load('roles');
        }

        return $organizationParticipant;
    }

    public function updateParticipantRole(int $participantId, int $roleId): OrganizationParticipant|bool|null
    {
        $participant = OrganizationParticipant::find($participantId);

        if (!$participant) {
            return null;
        }

        if ($participant->model_type !== \App\Models\User::class) {
            return false;
        }

        $organizationParticipant = $this->updateUserRole(
            organizationId: $participant->organization_id,
            userId: $participant->model_id,
            roleId: $roleId
        );

        return $organizationParticipant;
    }

    public function delete(int $id)
    {
        $participant = OrganizationParticipant::find($id);

        if (! $participant) {
            return [
                'success' => false,
                'message' => 'Запись не найдена',
                'code' => 404,
            ];
        }

        $organizationId = $participant->organization_id;

        app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);
        $isOrganization = $participant->model_type == \App\Models\Client::class;
        $modelId = $participant->model_id;

        if ($isOrganization) {
            $userIds = DB::table('organization_participants')
                ->where('organization_id', $modelId)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id');

            if ($userIds->isNotEmpty()) {
                User::whereIn('id', $userIds)
                    ->chunkById(500, function ($users) use ($organizationId) {
                        foreach ($users as $user) {
                            $hasDirectLink = DB::table('organization_participants')
                                ->where('organization_id', $organizationId)
                                ->where('model_type', \App\Models\User::class)
                                ->where('model_id', $user->id)
                                ->exists();

                            if ($hasDirectLink) {
                                Log::info('Пропущен сброс ролей: пользователь связан напрямую с родительской организацией', [
                                    'user_id' => $user->id,
                                    'organization_id' => $organizationId,
                                ]);
                                continue;
                            }

                            $user->syncRoles([]);
                        }
                    });
            }
        } else {
            if ($participant->model_type == \App\Models\User::class) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);
                $participant->model->syncRoles([]);
            }
        }

        $participant->delete();
        return [
            'success' => true,
            'message' => 'Запись удалена',
            'code' => 200,
        ];
    }

    public function getClientsByUser(int $userId)
    {
        $direct = DB::table('organization_participants as op')
            ->join('clients as org', 'org.id', '=', 'op.organization_id')
            ->where(function ($q) use ($userId) {
                $q->where('op.model_type', \App\Models\User::class)
                    ->where('op.model_id', $userId);
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
                $q->where('staff.model_type', \App\Models\User::class)->where('staff.model_id', $userId);
            })
            ->select('client.id', 'client.name');

        $clients = $direct->union($viaFulfillment)->get();

        return $clients;
    }

    public function getUsersByClient(int $clientId)
    {
        if (!$clientId) {
            return null;
        }

        $directUsers = DB::table('organization_participants as op')
            ->join('users as u', 'u.id', '=', 'op.model_id')
            ->where('op.organization_id', $clientId)
            ->where(function ($q) {
                $q->where('op.model_type', \App\Models\User::class);
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

        return $result;
    }

    public function getInvitations($user, $clientId)
    {
        return Invitation::join('clients', 'invitations.client_id', '=', 'clients.id')
            ->join('users', 'invitations.inviter_id', '=', 'users.id')
            ->join('roles', 'invitations.role_id', '=', 'roles.id')
            ->select([
                'invitations.id',
                'invitations.email',
                'invitations.created_at',
                'invitations.expires_at',
                'invitations.revoked_at',
                'invitations.accepted_at',
                'clients.name as client_name',
                'clients.id as client_id',
                'users.name as inviter_name',
                'users.id as inviter_id',
                'roles.id as role_id',
                'roles.name as role_name',
                'roles.visible_name as role_visible_name',
            ])
            ->where('invitations.client_id', $clientId)
            ->latest('invitations.created_at')
            ->get()
            ->map(function ($invitation) {
                $now = now();
                $expiresAt = $invitation->expires_at;
                $revokedAt = $invitation->revoked_at;
                $acceptedAt = $invitation->accepted_at;

                if ($revokedAt) {
                    $status = [
                        'name' => 'revoked',
                        'visible_name' => 'Отменено',
                    ];
                } elseif ($acceptedAt) {
                    $status = [
                        'name' => 'accepted',
                        'visible_name' => 'Принято',
                    ];
                } elseif ($expiresAt && $now->gt($expiresAt)) {
                    $status = [
                        'name' => 'expired',
                        'visible_name' => 'Просрочено',
                    ];
                } else {
                    $status = [
                        'name' => 'invited',
                        'visible_name' => 'Приглашен',
                    ];
                }

                return [
                    'type' => 'invitation',
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'name' => null,
                    'status' => $status,
                    'client' => [
                        'id' => $invitation->client_id,
                        'name' => $invitation->client_name,
                    ],
                    'role' => [
                        'id' => $invitation->role_id,
                        'name' => $invitation->role_name,
                        'visible_name' => $invitation->role_visible_name,
                    ],
                    'created_at' => $invitation->created_at,
                    'expires_at' => $invitation->expires_at,
                    'inviter' => [
                        'id' => $invitation->inviter_id,
                        'name' => $invitation->inviter_name,
                    ],
                ];
            });
    }

    public function updateUserRole(int $organizationId, int $userId, int $roleId): OrganizationParticipant
    {
        $organizationParticipant = OrganizationParticipant::where('organization_id', $organizationId)
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', $userId)
            ->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);

        $role = Role::findOrFail($roleId);

        $user = \App\Models\User::findOrFail($userId);
        $user->syncRoles([$role->name]);

        $organizationParticipant->load('user', 'client');

        return $organizationParticipant;
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

    private function normalizeModelType(string $type): string
    {
        $t = trim($type);
        $lc = strtolower($t);

        if (in_array($lc, ['user', 'users'], true) || $t === \App\Models\User::class) {
            return \App\Models\User::class;
        }
        if (in_array($lc, ['client', 'organization', 'org'], true) || $t === \App\Models\Client::class) {
            return \App\Models\Client::class;
        }

        throw new \InvalidArgumentException("Unknown model_type: {$type}");
    }

    private function userHasAnyRoleInOrg(int $organizationId, int $userId): bool
    {
        return \DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', $userId)
            ->where('client_id', $organizationId)
            ->exists();
    }

    public function propagateUserFromFulfillmentToParents(int $fulfillmentId, int $userId): void
    {
        $fulfillment = Client::withoutGlobalScopes()->findOrFail($fulfillmentId);
        if (! $fulfillment->is_fulfillment) {
            return;
        }

        $roleIdInFulfillment = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->where('client_id', $fulfillmentId)
            ->value('role_id');

        if (! $roleIdInFulfillment) {
            return;
        }

        $mappedRoleId = $this->mapFulfillmentRoleId((int)$roleIdInFulfillment);
        $mappedRole = Role::find($mappedRoleId);
        if (! $mappedRole) {
            Log::warning('Не найдена mapped-роль для прокидывания из фулфилмента', [
                'fulfillment_id' => $fulfillmentId,
                'user_id'        => $userId,
                'mapped_role_id' => $mappedRoleId,
            ]);
            return;
        }

        $parentOrgIds = OrganizationParticipant::query()
            ->where('model_type', Client::class)
            ->where('model_id', $fulfillmentId)
            ->pluck('organization_id')
            ->unique()
            ->all();

        if (empty($parentOrgIds)) {
            return;
        }

        DB::transaction(function () use ($parentOrgIds, $userId, $mappedRole) {
            foreach ($parentOrgIds as $parentOrgId) {
                $exists = OrganizationParticipant::where([
                    'organization_id' => (int)$parentOrgId,
                    'model_type'      => User::class,
                    'model_id'        => (int)$userId,
                ])->exists();

                if ($exists) {
                    continue;
                }

                if ($this->userHasAnyRoleInOrg((int)$parentOrgId, (int)$userId)) {
                    continue;
                }

                app(PermissionRegistrar::class)->setPermissionsTeamId((int)$parentOrgId);
                $user = User::find($userId);
                if ($user) {
                    $user->assignRole($mappedRole->name);
                }
            }
        });
    }
}
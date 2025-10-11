<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\OrganizationParticipant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class OrganizationParticipantService
{
    public function create(array $data): OrganizationParticipant
    {
        $organizationParticipant = OrganizationParticipant::create([
            'organization_id' => $data['organization_id'],
            'model_type' => User::class,
            'model_id' => $data['user_id'],
        ]);

        if (! empty($data['role'])) {
            $role = Role::findOrFail($data['role']);
            app(PermissionRegistrar::class)->setPermissionsTeamId($data['organization_id']);
            $organizationParticipant->model->assignRole($role->name);
        }

        return $organizationParticipant->load('model', 'organization');
    }

    public function update(OrganizationParticipant $organizationParticipant, array $data): OrganizationParticipant
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organizationParticipant->client_id);
        if (! empty($data['role_id'])) {
            $role = Role::findOrFail($data['role_id']);
            $organizationParticipant->user->syncRoles([$role->name]);
        }

        $organizationParticipant->load('user', 'client');
        $organizationParticipant->user->load('roles');
        $role = $organizationParticipant->user->roles->first();
        $organizationParticipant->role_id = $role->id;
        $organizationParticipant->role = $role;

        return $organizationParticipant;
    }

    public function delete(OrganizationParticipant $participant): void
    {
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
            if ($participant->model_type === \App\Models\User::class) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);
                $participant->model->syncRoles([]);
            }
        }

        $participant->delete();
    }

    public function getClientsByUser($userId)
    {
        return OrganizationParticipant::where('model_type', User::class)
            ->where('model_id', $userId)
            ->join('clients', 'organization_participants.organization_id', '=', 'clients.id')
            ->select('clients.id', 'clients.name')
            ->get();
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

    public function getUsers($organizationId)
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);

        return OrganizationParticipant::where('organization_id', $organizationId)
            ->where('model_type', User::class)
            ->with('model:id,name,email,phone,address,avatar,created_at')
            ->get()
            ->map(function ($participant) {
                $user = $participant->model;
                $role = $user->roles->first();

                return [
                    'type' => 'user',
                    'id' => $participant->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'status' => [
                        'name' => 'active',
                        'visible_name' => 'Активный',
                    ],
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'avatar' => $user->avatar,
                    'created_at' => $user->created_at,
                    'role' => $role ? [
                        'id' => $role->id,
                        'name' => $role->name,
                        'visible_name' => $role->visible_name ?? $role->name,
                    ] : null,
                ];
            });
    }
}

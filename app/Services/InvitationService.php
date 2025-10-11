<?php

namespace App\Services;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class InvitationService
{
    public function accept(string $token): array
    {
        $user = Auth::user();
        $inv = Invitation::where('token', $token)->first();

        if (! $inv || ($inv->email !== $user->email)) {
            return [
                'success' => false,
                'message' => 'Приглашение не найдено',
            ];
        }

        if (! $inv->isActive()) {
            return [
                'success' => false,
                'message' => 'Приглашение недействительно',
            ];
        }

        return DB::transaction(function () use ($inv) {
            $user = User::where('email', $inv->email)->first();

            $existingParticipant = DB::table('organization_participants')
                ->where('organization_id', $inv->client_id)
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', $user?->id)
                ->first();

            if ($existingParticipant) {
                return [
                    'success' => false,
                    'message' => 'Пользователь уже добавлен в эту организацию',
                ];
            }

            DB::table('organization_participants')->upsert([[
                'organization_id' => $inv->client_id,
                'model_type' => \App\Models\User::class,
                'model_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]], uniqueBy: ['organization_id', 'model_type', 'model_id'], update: ['updated_at']);
            $role = Role::findOrFail($inv->role_id);
            app(PermissionRegistrar::class)->setPermissionsTeamId($inv->client_id);
            $user->syncRoles([$role->name]);

            $inv->accepted_at = now();
            $inv->save();

            return [
                'success' => true,
                'message' => 'Приглашение принято',
            ];
        });
    }

    public function create(int $clientId, int $inviterId, string $email, int $roleId, ?array $meta = null): array
    {
        $role = Role::findOrFail($roleId);
        $email = mb_strtolower($email);
        $user = User::where('email', $email)->first();
        $isNewUser = ! $user;

        if ($user) {
            $exists = DB::table('organization_participants')
                ->where('organization_id', $clientId)
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', $user->id)
                ->exists();

            if ($exists) {
                return [
                    'message' => 'Пользователь уже добавлен в эту организацию',
                    'mail_sent' => false,
                    'status' => 'already_exists',
                ];
            }

            $hasRole = DB::table('model_has_roles')
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', $user->id)
                ->where('client_id', $clientId)
                ->exists();

            // TO DO: временно ограничиваем — нельзя пригласить, если есть хотя бы одна роль
            if ($hasRole) {
                return [
                    'message' => 'Пользователь уже имеет роль в этой организации',
                    'mail_sent' => false,
                    'status' => 'role_exists',
                ];
            }
        }

        Invitation::where('client_id', $clientId)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $inv = Invitation::create([
            'client_id' => $clientId,
            'inviter_id' => $inviterId,
            'email' => $email,
            'role_id' => $roleId,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'meta' => $meta,
        ]);

        $result = [
            'message' => '',
            'status' => 'invitation_created',
            'mail_sent' => false,
        ];

        try {
            Mail::to($inv->email)->send(new InvitationMail($inv, $isNewUser));
            $result['message'] = $user
                ? 'Приглашение отправлено существующему пользователю'
                : 'Приглашение отправлено новому пользователю';
            $result['mail_sent'] = true;
        } catch (\Exception $e) {
            $result['message'] = 'Ошибка отправки письма: '.$e->getMessage();
            $result['mail_sent'] = false;
        }

        return $result;
    }

    public function revoke(int $invitationId): void
    {
        Invitation::whereKey($invitationId)
            ->whereNull('accepted_at')
            ->update(['revoked_at' => now()]);
    }
}

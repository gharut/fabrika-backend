<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvitationMail;

class InvitationService
{
    public function accept(string $token, array $payload): User
    {
        /** @var Invitation $inv */
        $inv = Invitation::where('token',$token)->firstOrFail();
        if (!$inv->isActive()) abort(410, 'Приглашение недействительно');

        return DB::transaction(function () use ($inv, $payload) {
            // Ищем пользователя по email
            $user = User::where('email', $inv->email)->first();

            if (!$user) {
                $user = User::create([
                    'name'     => $payload['name'] ?? explode('@',$inv->email)[0],
                    'email'    => $inv->email,
                    'password' => Hash::make($payload['password'] ?? Str::random(16)),
                ]);
                $roleMap = [
                    2 => 'admin',
                    3 => 'manager', 
                    4 => 'logistics',
                ];
                
                $roleName = $roleMap[$inv->role_id] ?? 'user';
                $user->assignRole($roleName);
            }

            // Привязываем к клиенту с ролью (upsert)
            DB::table('client_users')->upsert([[
                'client_id' => $inv->client_id,
                'user_id'   => $user->id,
                'role_id'   => $inv->role_id,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]], uniqueBy: ['client_id','user_id'], update: ['role_id','updated_at']);

            // Закрываем приглашение
            $inv->accepted_at = now();
            $inv->save();

            return $user;
        });
    }

    public function revoke(int $invitationId): void
    {
        Invitation::whereKey($invitationId)
            ->whereNull('accepted_at')
            ->update(['revoked_at'=>now()]);
    }

    public function create(int $clientId, int $inviterId, string $email, int $roleId, ?array $meta = null): array
    {
        Invitation::where('client_id', $clientId)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $inv = Invitation::create([
            'client_id'  => $clientId,
            'inviter_id' => $inviterId,
            'email'      => mb_strtolower($email),
            'role_id'    => $roleId,
            'token'      => Str::random(64),
            'expires_at' => now()->addDays(7),
            'meta'       => $meta,
        ]);

        $result = [
            'message' => '',
        ];

        try {
            Mail::to($inv->email)->send(new InvitationMail($inv));
            $result['message'] = 'Приглашение отправлено успешно';
            $result['mail_sent'] = true;
        } catch (\Exception $e) {
            $result['message'] = 'Ошибка отправки письма: ' . $e->getMessage();
            $result['mail_sent'] = false;
        }

        return $result;
    }
}
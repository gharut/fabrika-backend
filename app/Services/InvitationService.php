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
        $inv = Invitation::where('token', $token)->firstOrFail();
        if (!$inv->isActive()) abort(410, 'Приглашение недействительно');

        return DB::transaction(function () use ($inv, $payload) {
            $user = User::where('email', $inv->email)->first();
            
            $existingClientUser = DB::table('client_users')
                ->where('client_id', $inv->client_id)
                ->where('user_id', $user?->id)
                ->first();
                
            if ($existingClientUser) {
                abort(409, 'Пользователь уже добавлен в эту организацию');
            }

            if (!$user) {
                if (empty($payload['password'])) {
                    abort(422, 'Пароль обязателен для нового пользователя');
                }

                $user = User::create([
                    'name'     => $payload['name'] ?? explode('@', $inv->email)[0],
                    'email'    => $inv->email,
                    'password' => Hash::make($payload['password']),
                ]);

                $user->assignRole('user');
            }

            DB::table('client_users')->upsert([[
                'client_id' => $inv->client_id,
                'user_id'   => $user->id,
                'role_id'   => $inv->role_id,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]], uniqueBy: ['client_id','user_id'], update: ['role_id','updated_at']);

            $inv->accepted_at = now();
            $inv->save();

            return $user;
        });
    }

    public function create(int $clientId, int $inviterId, string $email, int $roleId, ?array $meta = null): array
    {
        $email = mb_strtolower($email);
        $user = User::where('email', $email)->first();
        
        if ($user) {
            $existingClientUser = DB::table('client_users')
                ->where('client_id', $clientId)
                ->where('user_id', $user->id)
                ->first();
                
            if ($existingClientUser) {
                return [
                    'message' => 'Пользователь уже добавлен в эту организацию',
                    'mail_sent' => false,
                    'status' => 'already_exists'
                ];
            }
        }
        
        Invitation::where('client_id', $clientId)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $inv = Invitation::create([
            'client_id'  => $clientId,
            'inviter_id' => $inviterId,
            'email'      => $email,
            'role_id'    => $roleId,
            'token'      => Str::random(64),
            'expires_at' => now()->addDays(7),
            'meta'       => $meta,
        ]);

        $result = [
            'message' => '',
            'status' => 'invitation_created'
        ];

        try {
            Mail::to($inv->email)->send(new InvitationMail($inv));
            $result['message'] = $user 
                ? 'Приглашение отправлено существующему пользователю' 
                : 'Приглашение отправлено новому пользователю';
            $result['mail_sent'] = true;
        } catch (\Exception $e) {
            $result['message'] = 'Ошибка отправки письма: ' . $e->getMessage();
            $result['mail_sent'] = false;
        }

        return $result;
    }

    public function revoke(int $invitationId): void
    {
        Invitation::whereKey($invitationId)
            ->whereNull('accepted_at')
            ->update(['revoked_at'=>now()]);
    }
}
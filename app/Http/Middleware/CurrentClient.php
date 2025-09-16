<?php

namespace App\Http\Middleware;

use App\Models\ClientUser;
use App\Support\ClientContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\PermissionRegistrar;

class CurrentClient
{
    public function __construct(private ClientContext $clientContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $isSuper = $user->hasRole('super-admin');

        // Получаем client_id из разных источников
        $clientId = $request->header('X-Client-Id') 
            ?? $request->route('client_id') 
            ?? $user->last_selected_client_id;

        // Если client_id не задан, определяем автоматически
        if (!$clientId) {
            $clientIds = ClientUser::where('user_id', $user->id)
                ->pluck('client_id')
                ->all();

            if (count($clientIds) === 1) {
                $clientId = $clientIds[0];
            } else if (count($clientIds) > 1) {
                return response()->json([
                    'message' => 'Client ID is required',
                    'available_client_ids' => $clientIds,
                    'endpoint' => '/api/user/clients'
                ], 400);
            } elseif (!$isSuper) {
                abort(403, 'User is not associated with any client');
            }
        }

        // Проверка членства (супер-админ может обойти)
        if (!$isSuper) {
            $isMember = ClientUser::where('client_id', $clientId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'message' => 'You are not a member of this client',
                ], 403);
            }
        }

        $this->clientContext->set($clientId ? (int)$clientId : null);
        $request->attributes->set('client_id', $clientId);
        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);

        // Обновляем последний выбранный клиент
        // if ($clientId && $user->last_selected_client_id !== $clientId) {
        //     $user->update(['last_selected_client_id' => $clientId]);
        // }

        return $next($request);
    }
}
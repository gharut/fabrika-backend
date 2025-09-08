<?php

namespace App\Http\Middleware;

use App\Models\ClientUser;
use App\Support\ClientContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CurrentClient
{
    public function __construct(private ClientContext $clientContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if ($user->hasRole('super-admin')) {
            return $next($request);
        }
        
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
                    'endpoint' => '/api/user/clients' // Эндпоинт для получения клиентов
                ], 400);
            } else {
                abort(403, 'User is not associated with any client');
            }
        }

        // Проверяем принадлежность пользователя к клиенту
        $isMember = ClientUser::where('client_id', $clientId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            abort(403, 'You are not a member of this client');
        }

        // Устанавливаем контекст
        $this->clientContext->set((int)$clientId);
        $request->attributes->set('client_id', $clientId);

        // Обновляем последний выбранный клиент
        if ($user->last_selected_client_id !== $clientId) {
            $user->update(['last_selected_client_id' => $clientId]);
        }

        return $next($request);
    }
}
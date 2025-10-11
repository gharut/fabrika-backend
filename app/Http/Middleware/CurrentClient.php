<?php

namespace App\Http\Middleware;

use App\Models\OrganizationParticipant;
use App\Support\ClientContext;
use Closure;
use App\Models\User as UserModel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;

class CurrentClient
{
    public function __construct(private ClientContext $clientContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $isSuper = $user->isSuperAdmin();

        $clientId = $request->header('X-Client-Id')
            ?? $request->route('client_id')
            ?? null;

        if (!$clientId) {
            $candidateIds = $this->candidateClientIdsForUser($user->id);

            if ($candidateIds->count() === 1) {
                $clientId = (int) $candidateIds->first();
            } elseif ($candidateIds->count() > 1) {
                return response()->json([
                    'message' => 'Client ID is required',
                    'available_client_ids' => $candidateIds->values(),
                    'endpoint' => '/api/user/clients',
                ], 400);
            } elseif (!$isSuper) {
                abort(403, 'User is not associated with any client [' . UserModel::class . ':' . $user->id . ']');
            }
        }

        if (!$isSuper && $clientId) {
            $allowed = $this->userBelongsToClient($user->id, (int) $clientId);

            if (!$allowed) {
                return response()->json([
                    'message' => 'User is not associated with any client [' . UserModel::class . ':' . $user->id . ']',
                ], 403);
            }
        }

        $this->clientContext->set($clientId ? (int)$clientId : null);
        $request->attributes->set('client_id', $clientId);
        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);

        return $next($request);
    }

    private function candidateClientIdsForUser(int $userId)
    {
        $direct = DB::table('organization_participants as p')
            ->join('clients as c', 'c.id', '=', 'p.organization_id')
            ->where('p.model_type', UserModel::class)
            ->where('p.model_id', $userId)
            ->select('c.id');

        $viaFulfillment = DB::table('organization_participants as staff')
            ->join('clients as f', 'f.id', '=', 'staff.organization_id')
            ->join('organization_participants as link', function ($j) {
                $j->on('link.model_id', '=', 'f.id');
            })
            ->join('clients as client', 'client.id', '=', 'link.organization_id')
            ->where('f.is_fulfillment', 1)
            ->where('staff.model_type', UserModel::class)
            ->where('staff.model_id', $userId)
            ->select('client.id');

        $ids = DB::query()
            ->fromSub(function ($q) use ($direct, $viaFulfillment) {
                $q->from($direct->unionAll($viaFulfillment), 't');
            }, 'u')
            ->select('u.id')
            ->distinct()
            ->pluck('id');

        return $ids;
    }

    private function userBelongsToClient(int $userId, int $clientId): bool
    {
        return DB::table('clients as c')
            ->where('c.id', $clientId)
            ->where(function ($q) use ($userId) {
                $q->whereExists(function ($sq) use ($userId) {
                    $sq->from('organization_participants as p')
                        ->whereColumn('p.organization_id', 'c.id')
                        ->where('p.model_type', UserModel::class)
                        ->where('p.model_id', $userId);
                })
                ->orWhereExists(function ($sq) use ($userId) {
                    $sq->from('organization_participants as staff')
                        ->join('clients as f', 'f.id', '=', 'staff.organization_id')
                        ->join('organization_participants as link', function ($j) {
                            $j->on('link.model_id', '=', 'f.id')
                              ->on('link.organization_id', '=', 'c.id');
                        })
                        ->where('f.is_fulfillment', 1)
                        ->where('staff.model_type', UserModel::class)
                        ->where('staff.model_id', $userId);
                });
            })
            ->exists();
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Promo\PromoStartRequest;
use App\Http\Requests\Api\Promo\PromoRevertRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\PromoService;

class PromoController extends Controller
{
    public function __construct(private PromoService $service) {}

    public function start(PromoStartRequest $req): JsonResponse
    {
        $dto = $req->validated();
        $result = $this->service->start($dto);
        return response()->json($result);
    }

    public function revert(PromoRevertRequest $req): JsonResponse
    {
        $dto = $req->validated();
        $result = $this->service->revert((int)$dto['campaignId'], $dto['wbToken']);
        return response()->json($result);
    }

    public function status(Request $req)
    {
        $id = (int)$req->query('campaignId');
        abort_unless($id, 422, 'campaignId required');
        return $this->service->status($id); // вернуть агрегаты и прогресс
    }
}


<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConsumableActions;
use App\Enums\ConsumableDeliveryTypes;
use App\Enums\ConsumablePaymentTypes;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Consumables\ConsumableCreateRequest;
use App\Models\Consumable;
use App\Models\ConsumableHistory;
use App\Repositories\Interfaces\ConsumableRepositoryInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableHistoryController extends Controller
{
    public function __construct(protected ConsumableRepositoryInterface $consumableRepository)
    {
    }

    public function list(): JsonResponse
    {
        $consumables = ConsumableHistory::query()
            ->whereNot(
                function (Builder $query) {
                    $query->where( ['type' => ConsumableActions::IN->value, 'delivery_status' => ConsumableDeliveryTypes::NOT_DELIVERED->value]);
                }

            )
            ->with(['consumable:id,name,size,unit,price','supplier:id,name'])
            ->orderByDesc('created_at')
            ->paginate();
        return response()->json($consumables);
    }

    public function listWaiting(): JsonResponse
    {
        $consumables = ConsumableHistory::query()
            ->where(['type' => ConsumableActions::IN->value])
            ->where(function (Builder $query) {
                $query->orWhere( ['payment_status' => ConsumablePaymentTypes::NOT_PAID->value, 'delivery_status' => ConsumableDeliveryTypes::NOT_DELIVERED->value]);
            })
            ->with(['consumable:id,name,size,unit,price','supplier:id,name'])
            ->orderByDesc('created_at')->get();
        return response()->json($consumables);
    }

    public function setPaid(ConsumableHistory $consumableHistory): JsonResponse
    {
        $consumableHistory->payment_status = ConsumablePaymentTypes::PAID->value;
        return response()->json(['success' => $consumableHistory->save()]);
    }

    public function setDelivered(ConsumableHistory $consumableHistory): JsonResponse
    {
        $consumableHistory->delivery_status = ConsumableDeliveryTypes::DELIVERED->value;
        $saved = $consumableHistory->save();
        if($saved) {
            $this->consumableRepository->incrementConsumable($consumableHistory->consumable_id, $consumableHistory->qty);
        }

        return response()->json(['success' => $saved]);
    }
}

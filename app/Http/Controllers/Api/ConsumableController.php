<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConsumableActions;
use App\Enums\ConsumableDeliveryTypes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Consumables\ConsumableCreateRequest;
use App\Http\Requests\Api\Consumables\ConsumableIncomeRequest;
use App\Http\Requests\Api\Consumables\ConsumableUpdateRequest;
use App\Models\Consumable;
use App\Models\ConsumableHistory;
use App\Models\Supplier;
use App\Models\Tag;
use App\Repositories\Interfaces\ConsumableRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsumableController extends Controller
{

    public function __construct(protected ConsumableRepositoryInterface $consumableRepository)
    {

    }
    /**
     * Display a listing of the resource.
     */
    public function list(): JsonResponse
    {
        $consumables = Consumable::all()->load("tags");
        return response()->json($consumables);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function inout(ConsumableIncomeRequest $request): JsonResponse
    {
        $saved = false;

        $history_item = new ConsumableHistory();
        $history_item->fill($request->all());

        DB::beginTransaction();
        try{
            $saved = $history_item->save();
            if($saved) {
                $consumable = Consumable::find($history_item->consumable_id);
                if($history_item->type == ConsumableActions::IN->value) {
                    Supplier::find($history_item->supplier_id)->tags()->syncWithoutDetaching($consumable->tags->modelKeys());
                    if($history_item->delivery_status == ConsumableDeliveryTypes::DELIVERED->value) {
                        $this->consumableRepository->incrementConsumable($history_item->consumable_id, $history_item->qty);
                    }
                }


                if($history_item->type == ConsumableActions::FIX_IN->value) {
                    $this->consumableRepository->incrementConsumable($history_item->consumable_id, $history_item->qty);
                }


                if($history_item->type == ConsumableActions::OUT->value
                    || $history_item->type == ConsumableActions::FIX_OUT->value
                    || $history_item->type == ConsumableActions::WASTE->value
                ) {
                    $this->consumableRepository->decrementConsumable($history_item->consumable_id, $history_item->qty);
                }
            }
            DB::commit();
        }catch (\Exception $e) {
            print_r($e->getMessage());
            DB::rollBack();
            $saved = false;
        }

        return \response()->json([
            'success' => $saved,
            'data' => $history_item->load('consumable')
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConsumableCreateRequest $request): JsonResponse
    {
        $consumable = new Consumable();
        $consumable->fill($request->except("tags"));
        $saved = $consumable->save();
        if($saved) {
            $consumable->tags()->attach($request->get("tags"));
        }

        return response()->json([
            "success" => $saved,
            "data" => $saved ? $consumable->load('tags') : [],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Consumable $consumable)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Consumable $consumable)
    {
        //
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(ConsumableUpdateRequest $request, Consumable $consumable)
    {
        $consumable->fill($request->only("name", "size", "unit", "price", "price_threshold", "price_threshold_type", "qty_threshold"));
        $saved = $consumable->save();
        if($saved) {
            $consumable->tags()->detach();
            $consumable->tags()->attach($request->get("tags"));
        }

        return response()->json([
            "success" => $saved,
            "data" => $saved ? $consumable->load('tags') : [],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Consumable $consumable)
    {
        $consumable->tags()->detach();
        return response()->json([
            "success" => $consumable->delete(),
        ]);
    }
}

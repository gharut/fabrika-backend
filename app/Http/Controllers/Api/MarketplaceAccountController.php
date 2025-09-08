<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\MarketplaceAccount\MarketplaceAccountCreateRequest;
use App\Http\Requests\Api\MarketplaceAccount\MarketplaceAccountUpdateRequest;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceAccount;
use App\Services\MarketplaceAccountService;
use Illuminate\Http\JsonResponse;

class MarketplaceAccountController extends Controller
{
    public function __construct(
        protected MarketplaceAccountService $service
    ) {}

    public function index(): JsonResponse
    {
        $items = MarketplaceAccount::with('client')->get();
        return response()->json($items);
    }

    public function store(MarketplaceAccountCreateRequest $request): JsonResponse
    {
        $item = $this->service->create($request->validated());
        return response()->json($item, 201);
    }

    public function show($id): JsonResponse
    {
        $marketplaceAccount = MarketplaceAccount::with('client')->find($id);
            
        return response()->json($marketplaceAccount);
    }

    public function update(MarketplaceAccountUpdateRequest $request, $id): JsonResponse
    {
        $marketplaceAccount = MarketplaceAccount::find($id);
        
        if (!$marketplaceAccount) {
            return response()->json(['error' => 'Marketplace account not found'], 404);
        }
        
        $item = $this->service->update($marketplaceAccount, $request->validated());
        return response()->json($item);
    }

    public function destroy($id): JsonResponse
    {
        $marketplaceAccount = MarketplaceAccount::find($id);
        
        if (!$marketplaceAccount) {
            return response()->json(['error' => 'Marketplace account not found'], 404);
        }
        
        $this->service->delete($marketplaceAccount);
        return response()->json(null, 204);
    }
}

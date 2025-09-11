<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\WB\WbService;

class WbController extends Controller
{
    public function __construct(
        private WbService $wbService
    ) {}

    public function import(Request $request)
    {
        $data = $request->validate([
            'marketplace_account_id' => 'required|integer|exists:marketplace_accounts,id',
            'limit'     => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $data['limit'] ?? 100;
        $marketplaceAccountId = $data['marketplace_account_id'];

        $result = $this->wbService->importWbProducts($marketplaceAccountId, $limit);
        $status = $result['success'] ? 200 : 422;
        return response()->json($result, $status);
    }
}

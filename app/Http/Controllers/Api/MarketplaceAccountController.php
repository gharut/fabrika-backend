<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\MarketplaceAccount\MarketplaceAccountCreateRequest;
use App\Http\Requests\Api\MarketplaceAccount\MarketplaceAccountUpdateRequest;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceAccount;
use App\Services\MarketplaceAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MarketplaceAccountController extends Controller
{
    public function __construct(
        protected MarketplaceAccountService $service
    ) {}

    public function index(): JsonResponse
    {
        $items = MarketplaceAccount::get();
        return response()->json($items);
    }

    public function getMarketplacesList(): JsonResponse
    {
        try {
            $marketplaces = MarketplaceAccount::select('id', 'name')
                ->orderBy('name')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $marketplaces,
                'count' => $marketplaces->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch marketplaces list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка маркетплейсов',
            ], 500);
        }
    }

    public function store(MarketplaceAccountCreateRequest $request): JsonResponse
    {
        try {
            $item = $this->service->create($request->validated());
            return response()->json([
                'data' => $item,
                'success' => true,
            ], 201);           
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors)[0] ?? 'Ошибка валидации';
            
            return response()->json([
                'success' => false,
                'message' => $firstError
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось добавить магазин'
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $marketplaceAccount = MarketplaceAccount::with('client')->find($id);
            
        return response()->json($marketplaceAccount);
    }

    public function update(MarketplaceAccountUpdateRequest $request, $id): JsonResponse
    {
        try {
            $marketplaceAccount = MarketplaceAccount::find($id);
            
            if (!$marketplaceAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Магазин не найден'
                ], 404);
            }
            
            $item = $this->service->update($marketplaceAccount, $request->validated());
            return response()->json([
                'data' => $item,
                'success' => true,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'messages' => $e->errors()
            ], 422);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить аккаунт'
            ], 500);
        }
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

    public function checkConnection($id): JsonResponse
    {
        try {
            $account = MarketplaceAccount::findOrFail($id);
            
            $isConnected = $account->checkConnection();
            
            return response()->json([
                'success' => $isConnected,
                'message' => $isConnected 
                    ? 'Аккаунт успешно подключен' 
                    : $account->error_message,
                'is_connected' => $isConnected,
                'account' => [
                    'id' => $account->id,
                    'status' => $account->status,
                    'error_message' => $account->error_message,
                    'last_checked_at' => $account->last_checked_at?->toISOString()
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Marketplace account not found', ['id' => $id]);
            
            return response()->json([
                'success' => false,
                'message' => 'Аккаунт не найден'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Marketplace connection check failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке подключения: ' . $e->getMessage()
            ], 500);
        }
    }
}

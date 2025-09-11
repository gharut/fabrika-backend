<?php

use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TagsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\Api\MarketplaceAccountController;
use App\Http\Controllers\Api\ClientUserController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ChestnyZnakLabelController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/invitations/accept', [InvitationController::class, 'accept']);

Route::group([
    'middleware' => 'api',
    'namespace' => 'App\Http\Controllers\Api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('login',  [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('reset-password',[AuthController::class, 'passwordReset']);
    Route::post('request-password', [AuthController::class, 'requestPasswordReset']);
});

Route::group([
    'middleware'=> ['auth:sanctum'],
    'namespace' => 'App\Http\Controllers\Api',
], function() {
    /**
     * Tag endpoints
     */
    Route::post('/tag/', [TagsController::class, 'store']);
    Route::put('/tag/{tag}', [TagsController::class, 'update']);
    Route::middleware('can:list-tags')->get('/tag/', [TagsController::class, 'list']);
    Route::middleware('can:list-tags')->get('/tag/list', [TagsController::class, 'listWithCounts']);
    Route::middleware('can:delete-tags')->delete('/tag/{tag}', [TagsController::class, 'destroy']);
    Route::middleware('can:get-tags')->get('/tag/{tag}', [TagsController::class, 'get']);
    Route::middleware('can:list-suppliers')->get('/tag/{tag}/suppliers', [TagsController::class, 'listSuppliers']);
    Route::middleware('can:list-consumables')->get('/tag/{tag}/consumables', [TagsController::class, 'listConsumables']);



    /**
     * Supplier endpoints
     */
    Route::post('/supplier/', [SupplierController::class, 'store']);
    Route::get('/supplier/', [SupplierController::class, 'list']);
    Route::put('/supplier/{supplier}', [SupplierController::class, 'update']);
    Route::middleware('can:get-suppliers')->get('/supplier/{supplier}', [SupplierController::class, 'get']);
    Route::middleware('can:delete-suppliers')->delete('/supplier/{supplier}', [SupplierController::class, 'destroy']);
    Route::middleware('can:list-suppliers')->get('/supplier/list', [SupplierController::class, 'list']);

    /**
     * Profile endpoints
     */

    Route::get('/profile/', [ProfileController::class, 'get']);
    Route::put('/profile/', [ProfileController::class, 'update']);


    /**
     * Roles endpoints
     */
//    Route::post('/supplier/', [SupplierController::class, 'store']);
//    Route::put('/supplier/{supplier}', [SupplierController::class, 'update']);
    Route::middleware('can:list-roles')->get('/roles/', [\App\Http\Controllers\Api\RolesController::class, 'list']);
    Route::middleware('can:create-roles')->get('/roles/permissions/', [\App\Http\Controllers\Api\RolesController::class, 'getPermissions']);
    Route::middleware('can:get-roles')->get('/roles/{role}', [\App\Http\Controllers\Api\RolesController::class, 'get']);
    Route::middleware('can:delete-roles')->delete('/roles/{role}', [\App\Http\Controllers\Api\RolesController::class, 'destroy']);
    Route::middleware('can:create-roles')->post('/roles/', [\App\Http\Controllers\Api\RolesController::class, 'store']);
    Route::middleware('can:delete-roles')->put('/roles/{role}', [\App\Http\Controllers\Api\RolesController::class, 'update']);

    /**
     * Users endpoints
     */

    Route::middleware('can:list-users')->get('/users/', [\App\Http\Controllers\Api\UserController::class, 'list']);
    Route::post('/users/', [\App\Http\Controllers\Api\UserController::class, 'store']);
    Route::put('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'update']);
    Route::delete('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'destroy']);

    /**
     * Consumables endpoints
     */

    Route::middleware('can:list-consumables')->get('/consumables/', [\App\Http\Controllers\Api\ConsumableController::class, 'list']);
    Route::post('/consumables/', [\App\Http\Controllers\Api\ConsumableController::class, 'store']);
    Route::put('/consumables/{consumable}', [\App\Http\Controllers\Api\ConsumableController::class, 'update']);
    Route::middleware('can:delete-consumables')->delete('/consumables/{consumable}', [\App\Http\Controllers\Api\ConsumableController::class, 'destroy']);
    Route::post('/consumables/{consumable}/inout', [\App\Http\Controllers\Api\ConsumableController::class, 'inout']);

    Route::get('/inout', [\App\Http\Controllers\Api\ConsumableHistoryController::class, 'list']);
    Route::get('/inout/waiting', [\App\Http\Controllers\Api\ConsumableHistoryController::class, 'listWaiting']);

    Route::middleware('can:inout-consumables')->post('/inout/{consumableHistory}/paid', [\App\Http\Controllers\Api\ConsumableHistoryController::class, 'setPaid']);
    Route::middleware('can:inout-consumables')->post('/inout/{consumableHistory}/delivered', [\App\Http\Controllers\Api\ConsumableHistoryController::class, 'setDelivered']);


    Route::post('/services/', [\App\Http\Controllers\Api\ServiceController::class, 'store']);
    Route::put('/services/{service}', [\App\Http\Controllers\Api\ServiceController::class, 'update']);
    // Route::middleware('can:list-services')->get('/services/', [\App\Http\Controllers\Api\ServiceController::class, 'list']);
    Route::middleware('can:delete-services')->delete('/services/{service}', [\App\Http\Controllers\Api\ServiceController::class, 'destroy']);



    Route::post('/clients/', [\App\Http\Controllers\Api\ClientController::class, 'store']);
    Route::post('clients/filters', [\App\Http\Controllers\Api\ClientController::class, 'getAllFiltered']);
    Route::get('/clients/{client}', [\App\Http\Controllers\Api\ClientController::class, 'get']);
    Route::put('/clients/{client}', [\App\Http\Controllers\Api\ClientController::class, 'update']);
    Route::middleware('can:view-clients')->get('/clients/', [\App\Http\Controllers\Api\ClientController::class, 'list']);
    Route::middleware('can:delete-clients')->delete('/clients/{client}', [\App\Http\Controllers\Api\ClientController::class, 'destroy']);


    Route::get('/attributes/', [\App\Http\Controllers\Api\AttributesController::class, 'list']);

    Route::post('/settings/', [\App\Http\Controllers\Api\SettingController::class, 'store']);
    Route::put('/settings/{setting}', [\App\Http\Controllers\Api\SettingController::class, 'update']);
    Route::middleware('can:list-settings')->get('/settings/', [\App\Http\Controllers\Api\SettingController::class, 'list']);
    Route::middleware('can:delete-settings')->delete('/settings/{setting}', [\App\Http\Controllers\Api\SettingController::class, 'destroy']);
    Route::get('/settings/{setting}', [\App\Http\Controllers\Api\SettingController::class, 'getSettingById']);
    Route::get('/settings/name/{name}', [\App\Http\Controllers\Api\SettingController::class, 'getSettingByName']);
    Route::get('/settings/category/{category}', [\App\Http\Controllers\Api\SettingController::class, 'getSettingsByCategory']);


    Route::post('/orders/', [\App\Http\Controllers\Api\OrdersController::class, 'store']);
    Route::put('/orders/{order}/pickup', [\App\Http\Controllers\Api\OrdersController::class, 'updatePickup']);
    Route::put('/orders/{order}/packaging', [\App\Http\Controllers\Api\OrdersController::class, 'updatePackaging']);
    Route::put('/orders/{order}/status', [\App\Http\Controllers\Api\OrdersController::class, 'updateStatus']);
    Route::get('/orders/', [\App\Http\Controllers\Api\OrdersController::class, 'list']);
    Route::get('/orders/{id}', [\App\Http\Controllers\Api\OrdersController::class, 'getOrder']);

    Route::get('/warehouses/', [\App\Http\Controllers\Api\WarehouseController::class, 'index']);
    Route::get('/warehouses/get-selections', [\App\Http\Controllers\Api\WarehouseController::class, 'getSelections']);
    Route::post('/warehouses/', [\App\Http\Controllers\Api\WarehouseController::class, 'store']);
    Route::put('/warehouses/{id}', [\App\Http\Controllers\Api\WarehouseController::class, 'update']);
    Route::delete('/warehouses/{id}', [\App\Http\Controllers\Api\WarehouseController::class, 'destroy']);
    
    Route::get('/client-users/clients', [ClientUserController::class, 'getClientsByUser']);
    Route::middleware(['current.client'])->prefix('client-users')->group(function () {
        Route::get('/', [ClientUserController::class, 'index']);
        Route::post('/', [ClientUserController::class, 'store']);
        Route::get('/users', [ClientUserController::class, 'getUsersByClient']);
        // Route::get('/clients', [ClientUserController::class, 'getClientsByUser']);
        Route::put('{clientUser}', [ClientUserController::class, 'update']);
        Route::get('{clientUser}', [ClientUserController::class, 'show']);
        Route::delete('{clientUser}', [ClientUserController::class, 'destroy']);
    });

    Route::middleware(['current.client'])->prefix('wb')->group(function () {       
        Route::middleware(['can:import-wb-product'])
            ->post('/test', [\App\Http\Controllers\Api\WbController::class, 'import']);
    });

    Route::middleware(['current.client'])->prefix('marketplace-accounts')->group(function () {           
        Route::middleware(['can:view-marketplace-accounts'])->group(function () {
            Route::get('/', [MarketplaceAccountController::class, 'index']);
            Route::post('/check-connection/{id}', [MarketplaceAccountController::class, 'checkConnection']);
            Route::get('{marketplaceAccount}', [MarketplaceAccountController::class, 'show']);
        });
        
        Route::middleware(['can:create-marketplace-accounts'])
            ->post('/', [MarketplaceAccountController::class, 'store']);
        
        Route::middleware(['can:edit-marketplace-accounts'])
            ->put('/{marketplaceAccount}', [MarketplaceAccountController::class, 'update']);
        
        Route::middleware(['can:delete-marketplace-accounts'])
            ->delete('/{marketplaceAccount}', [MarketplaceAccountController::class, 'destroy']);
    });

    Route::prefix('printers')->group(function () {
        Route::get('/', [PrinterController::class, 'index']);
        Route::post('/', [PrinterController::class, 'store']);
        Route::get('{printer}', [PrinterController::class, 'show']);
        Route::put('{printer}', [PrinterController::class, 'update']);
        Route::delete('{printer}', [PrinterController::class, 'destroy']);
        Route::post('{printer}/sync-count', [PrinterController::class, 'syncCount']);
    });

    Route::middleware(['current.client'])->prefix('labels')->group(function () {
        Route::get('/', [LabelController::class, 'index']);
        Route::post('/', [LabelController::class, 'store']);
        Route::post('/filters', [LabelController::class, 'getAllFiltered']);
        Route::get('{label}', [LabelController::class, 'show']);
        Route::put('{label}', [LabelController::class, 'update']);
        Route::delete('{label}', [LabelController::class, 'destroy']);
    });
    
    
    Route::middleware(['current.client'])->prefix('invitations')->group(function () {
        Route::get('/', [InvitationController::class, 'index']);              // список (по клиенту)
        Route::post('/', [InvitationController::class, 'store']);             // создать
        Route::delete('{id}', [InvitationController::class, 'revoke']);       // отозвать
    });

    Route::get('/label-templates', [\App\Http\Controllers\Api\LabelTemplateController::class, 'index']);
    Route::post('/labels-pdf/print', [\App\Http\Controllers\Api\LabelDesignerController::class, 'print']);
    Route::post('/labels-pdf/preview', [\App\Http\Controllers\Api\LabelDesignerController::class, 'preview']);

    Route::post('/wb-promo/start', [\App\Http\Controllers\Api\PromoController::class, 'start']);
    Route::post('/wb-promo/revert', [\App\Http\Controllers\Api\PromoController::class, 'revert']);
    Route::get('/wb-promo/status', [\App\Http\Controllers\Api\PromoController::class, 'status']);

    Route::get('/products-img/{id}', [\App\Http\Controllers\Api\ProductImageController::class, 'all']);
    Route::get('/products-img/{id}', [\App\Http\Controllers\Api\ProductImageController::class, 'main']);

    // Route::apiResource('wb-products', \App\Http\Controllers\Api\WbProductController::class);
    Route::middleware(['current.client'])->group(function () {
        Route::apiResource('wb-products', \App\Http\Controllers\Api\WbProductController::class);
        Route::post('wb-products/sizes', [\App\Http\Controllers\Api\WbProductController::class, 'getAllWithSizes']);
    });
    
    Route::apiResource('product-sizes', \App\Http\Controllers\Api\ProductSizeController::class);
    Route::middleware(['current.client'])->apiResource('brands', \App\Http\Controllers\Api\BrandController::class);

    Route::prefix('chestny-znak-labels')->group(function () {
        Route::middleware(['can:view-cz'])->get('/', [ChestnyZnakLabelController::class, 'index']);
        Route::middleware(['can:download-pdf-cz'])->post('/download-pdf', [ChestnyZnakLabelController::class, 'downloadPdfLabels']);
        Route::middleware(['can:cz-defective'])->post('/defective', [ChestnyZnakLabelController::class, 'markAsUnused']);
        Route::middleware(['can:import-cz'])->post('/import', [ChestnyZnakLabelController::class, 'import']);
        Route::middleware(['can:replace-size-cz'])->post('/replace-size', [ChestnyZnakLabelController::class, 'replaceSize']);
    });
});

Route::group([
    'namespace' => 'App\Http\Controllers\Api',
], function() {
    Route::get('/services/', [\App\Http\Controllers\Api\ServiceController::class, 'list']); // TODO: Move in auth group
    Route::get('/orders/calculate/{id}', [\App\Http\Controllers\Api\OrdersController::class, 'calculate']);
});

Route::options('/{any}', function () {
    return response()->json([], 204);
})->where('any', '.*');
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TagsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\Api\MarketplaceAccountController;
use App\Http\Controllers\Api\ClientUserController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ChestnyZnakLabelController;
use App\Http\Controllers\Api\TaggableController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductSizeController;
use App\Http\Controllers\Api\MarketplaceCategoryController;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Gate;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

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
    Route::get('client-users/clients', [ClientUserController::class, 'getClientsByUser']);
    Route::get('profile/send-verification-email', [ProfileController::class, 'sendVerificationEmail']);
    Route::post('profile/verify-email', [ProfileController::class, 'verifyByToken']);
});

Route::group([
    'middleware'=> ['auth:sanctum', 'current.client'],
    'namespace' => 'App\Http\Controllers\Api',
], function() {
    Route::middleware('cper:import-wb-product')->post('wb/import-product', [\App\Http\Controllers\Api\WbController::class, 'import']);

    Route::post('change-password', [AuthController::class, 'changePassword']);

    Route::get('/marketplace-categories', [MarketplaceCategoryController::class, 'index']);

    Route::get('products-img/{id}', [\App\Http\Controllers\Api\ProductImageController::class, 'all']);
    Route::get('products-img/{id}', [\App\Http\Controllers\Api\ProductImageController::class, 'main']);
    
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'get']);
        Route::put('/', [ProfileController::class, 'update']);
    });

    Route::get('label-templates', [\App\Http\Controllers\Api\LabelTemplateController::class, 'index']);
    Route::post('labels-pdf/print', [\App\Http\Controllers\Api\LabelDesignerController::class, 'print']);
    Route::post('labels-pdf/preview', [\App\Http\Controllers\Api\LabelDesignerController::class, 'preview']);

    // TO DO: Акции в WB
    // Route::post('/wb-promo/start', [\App\Http\Controllers\Api\PromoController::class, 'start']);
    // Route::post('/wb-promo/revert', [\App\Http\Controllers\Api\PromoController::class, 'revert']);
    // Route::get('/wb-promo/status', [\App\Http\Controllers\Api\PromoController::class, 'status']);

    Route::prefix('wb-products')->group(function () {
        Route::middleware('cper:view-products')->post('sizes', [\App\Http\Controllers\Api\WbProductController::class, 'getAllWithSizes']);
        Route::middleware('cper:view-products')->get('/', [\App\Http\Controllers\Api\WbProductController::class, 'index']);
        Route::middleware('cper:create-products')->post('/', [\App\Http\Controllers\Api\WbProductController::class, 'store']);
        Route::middleware('cper:view-products')->get('{wb_product}', [\App\Http\Controllers\Api\WbProductController::class, 'show']);
        Route::middleware('cper:edit-products')->put('{wb_product}', [\App\Http\Controllers\Api\WbProductController::class, 'update']);
        Route::middleware('cper:delete-products')->delete('{wb_product}', [\App\Http\Controllers\Api\WbProductController::class, 'destroy']);
    });

    Route::prefix('product-sizes')->group(function () {
        Route::middleware('cper:view-product-sizes')->get('/', [ProductSizeController::class, 'index']);
        Route::middleware('cper:create-product-sizes')->post('/', [ProductSizeController::class, 'store']);
        Route::middleware('cper:view-product-sizes')->get('{product_size}', [ProductSizeController::class, 'show']);
        Route::middleware('cper:edit-product-sizes')->put('{product_size}', [ProductSizeController::class, 'update']);
        Route::middleware('cper:delete-product-sizes')->delete('{product_size}', [ProductSizeController::class, 'destroy']);
    });

    Route::prefix('brands')->group(function () {
        Route::middleware('cper:view-brands')->get('/', [\App\Http\Controllers\Api\BrandController::class, 'index']);
        Route::middleware('cper:create-brands')->post('/', [\App\Http\Controllers\Api\BrandController::class, 'store']);
        Route::middleware('cper:view-brands')->get('{brand}', [\App\Http\Controllers\Api\BrandController::class, 'show']);
        Route::middleware('cper:edit-brands')->put('{brand}', [\App\Http\Controllers\Api\BrandController::class, 'update']);
        Route::middleware('cper:delete-brands')->delete('{brand}', [\App\Http\Controllers\Api\BrandController::class, 'destroy']);
    });
    
    Route::prefix('users')->group(function () {
        Route::middleware('cper:list-users')->get('/', [UserController::class, 'list']);
        Route::middleware('cper:create-users')->post('/', [UserController::class, 'store']);
        Route::middleware('cper:edit-users')->put('{user}', [UserController::class, 'update']);
        Route::middleware('cper:delete-users')->delete('{user}', [UserController::class, 'destroy']);
    });

    Route::prefix('clients')->group(function () {
        Route::middleware('cper:create-clients')->post('/', [ClientController::class, 'store']);
        Route::middleware('cper:view-clients')->post('filters', [ClientController::class, 'getAllFiltered']);
        Route::middleware('cper:view-clients')->get('{client}', [ClientController::class, 'get']);
        Route::middleware('cper:edit-clients')->put('{client}', [ClientController::class, 'update']);
        Route::middleware('cper:view-clients')->get('/', [ClientController::class, 'list']);
        Route::middleware('cper:delete-clients')->delete('{client}', [ClientController::class, 'destroy']);
    });

    Route::prefix('tags')->group(function () {
        Route::get('/', [TagsController::class, 'list']);
        Route::get('{tag}', [TagsController::class, 'get']);
        Route::post('/', [TagsController::class, 'store']);
        Route::put('{tag}', [TagsController::class, 'update']);
        Route::delete('{tag}', [TagsController::class, 'destroy']);

        Route::get('{alias}/{id}', [TaggableController::class, 'list']);
        Route::post('{alias}/{id}/attach', [TaggableController::class, 'attach']);
        Route::post('{alias}/{id}/detach', [TaggableController::class, 'detach']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\RolesController::class, 'list']);
        Route::get('permissions', [\App\Http\Controllers\Api\RolesController::class, 'getPermissions']);
        Route::get('{role}', [\App\Http\Controllers\Api\RolesController::class, 'get']);
        Route::middleware('cper:delete-roles')->delete('{role}', [\App\Http\Controllers\Api\RolesController::class, 'destroy']);
        Route::middleware('cper:create-roles')->post('/', [\App\Http\Controllers\Api\RolesController::class, 'store']);
        Route::middleware('cper:edit-roles')->put('{role}', [\App\Http\Controllers\Api\RolesController::class, 'update']);
    });

    Route::prefix('client-users')->group(function () {
        Route::get('/', [ClientUserController::class, 'index']);
        Route::post('/', [ClientUserController::class, 'store']);
        Route::get('users', [ClientUserController::class, 'getUsersByClient']);
        Route::put('{clientUser}', [ClientUserController::class, 'update']);
        Route::get('{clientUser}', [ClientUserController::class, 'show']);
        Route::delete('{userId}', [ClientUserController::class, 'destroy']);
    });

    Route::prefix('marketplace-accounts')->group(function () {           
        Route::middleware('cper:view-marketplace-accounts')->get('/', [MarketplaceAccountController::class, 'index']);
        Route::middleware('cper:check-connection')->post('check-connection/{id}', [MarketplaceAccountController::class, 'checkConnection']);
        Route::middleware('cper:view-marketplace-accounts')->get('{marketplaceAccount}', [MarketplaceAccountController::class, 'show']);
        Route::middleware('cper:create-marketplace-accounts')->post('/', [MarketplaceAccountController::class, 'store']);
        Route::middleware('cper:edit-marketplace-accounts')->put('{marketplaceAccount}', [MarketplaceAccountController::class, 'update']);
        Route::middleware('cper:delete-marketplace-accounts')->delete('{marketplaceAccount}', [MarketplaceAccountController::class, 'destroy']);
    });

    Route::prefix('printers')->group(function () {
        Route::get('/', [PrinterController::class, 'index']);
        Route::post('/', [PrinterController::class, 'store']);
        Route::get('{printer}', [PrinterController::class, 'show']);
        Route::put('{printer}', [PrinterController::class, 'update']);
        Route::delete('{printer}', [PrinterController::class, 'destroy']);
        Route::post('{printer}/sync-count', [PrinterController::class, 'syncCount']);
    });

    Route::prefix('labels')->group(function () {
        Route::middleware('cper:view-labels')->get('/', [LabelController::class, 'index']);
        Route::middleware('cper:create-labels')->post('/', [LabelController::class, 'store']);
        Route::middleware('cper:view-labels')->post('filters', [LabelController::class, 'getAllFiltered']);
        Route::middleware('cper:view-labels')->get('{label}', [LabelController::class, 'show']);
        Route::middleware('cper:edit-labels')->put('{label}', [LabelController::class, 'update']);
        Route::middleware('cper:delete-labels')->delete('{label}', [LabelController::class, 'destroy']);
    });
    
    Route::prefix('chestny-znak-labels')->group(function () {
        Route::middleware('cper:view-cz')->get('/', [ChestnyZnakLabelController::class, 'index']);
        Route::middleware('cper:download-pdf-cz')->post('download-pdf', [ChestnyZnakLabelController::class, 'downloadPdfLabels']);
        Route::middleware('cper:defective-cz')->post('defective', [ChestnyZnakLabelController::class, 'markAsUnused']);
        Route::middleware('cper:import-cz')->post('import', [ChestnyZnakLabelController::class, 'import']);
        Route::middleware('cper:replace-size-cz')->post('replace-size', [ChestnyZnakLabelController::class, 'replaceSize']);
    });

    Route::prefix('invitations')->group(function () {
        Route::post('accept', [InvitationController::class, 'accept']);
        Route::get('/', [InvitationController::class, 'index']);
        Route::post('/', [InvitationController::class, 'store']);
        Route::delete('{id}', [InvitationController::class, 'revoke']);
    });



    // TO DO: Будущая функциональность
    Route::post('/supplier/', [SupplierController::class, 'store']);
    Route::get('/supplier/', [SupplierController::class, 'list']);
    Route::put('/supplier/{supplier}', [SupplierController::class, 'update']);
    Route::middleware('can:get-suppliers')->get('/supplier/{supplier}', [SupplierController::class, 'get']);
    Route::middleware('can:delete-suppliers')->delete('/supplier/{supplier}', [SupplierController::class, 'destroy']);
    Route::middleware('can:list-suppliers')->get('/supplier/list', [SupplierController::class, 'list']);

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
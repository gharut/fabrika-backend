<?php

use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TagsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;

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


Route::group([
    'middleware' => 'api',
    'namespace' => 'App\Http\Controllers\Api',
    'prefix' => 'auth'
], function ($router) {

    Route::post('login',  [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('reset-password',[AuthController::class, 'passwordReset']);

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
    Route::middleware('can:list-services')->get('/services/', [\App\Http\Controllers\Api\ServiceController::class, 'list']);
    Route::middleware('can:delete-services')->delete('/services/{service}', [\App\Http\Controllers\Api\ServiceController::class, 'destroy']);



    Route::post('/clients/', [\App\Http\Controllers\Api\ClientController::class, 'store']);
    Route::put('/clients/{client}', [\App\Http\Controllers\Api\ClientController::class, 'update']);
    Route::middleware('can:list-clients')->get('/clients/', [\App\Http\Controllers\Api\ClientController::class, 'list']);
    Route::middleware('can:delete-clients')->delete('/clients/{client}', [\App\Http\Controllers\Api\ClientController::class, 'destroy']);


    Route::get('/attribtes/', [\App\Http\Controllers\Api\AttributesController::class, 'list']);
});

//Route::post('/register', [AuthController::class, 'register']);

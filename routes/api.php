<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::resource('users', \App\Http\Controllers\UserController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::resource('roles', \App\Http\Controllers\RoleController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::resource('items', \App\Http\Controllers\ItemController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::resource('categories', \App\Http\Controllers\CategoryController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::resource('units-of-measurement', \App\Http\Controllers\UnitOfMeasurementController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
        ->parameters(['units-of-measurement' => 'unitOfMeasurement']);
    Route::resource('warehouses', \App\Http\Controllers\WarehouseController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('account-titles/sync', [\App\Http\Controllers\AccountTitleController::class, 'sync']);
    Route::get('account-titles', [\App\Http\Controllers\AccountTitleController::class, 'index']);
    Route::post('charges/sync', [\App\Http\Controllers\ChargeController::class, 'sync']);
    Route::get('charges', [\App\Http\Controllers\ChargeController::class, 'index']);

    Route::resource('stocks', \App\Http\Controllers\StockController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::resource('misc-receipts', \App\Http\Controllers\MiscellaneousReceiptController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
});

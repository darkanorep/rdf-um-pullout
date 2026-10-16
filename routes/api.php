<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::resource('users', \App\Http\Controllers\UserController::class);
    Route::resource('roles', \App\Http\Controllers\RoleController::class);
    Route::resource('items', \App\Http\Controllers\ItemController::class);
    Route::resource('categories', \App\Http\Controllers\CategoryController::class);
    Route::resource('units-of-measurement', \App\Http\Controllers\UnitOfMeasurementController::class)
        ->parameters(['units-of-measurement' => 'unitOfMeasurement']);
    Route::resource('warehouses', \App\Http\Controllers\WarehouseController::class);
    Route::post('account-titles/sync', [\App\Http\Controllers\AccountTitleController::class, 'sync']);
    Route::get('account-titles', [\App\Http\Controllers\AccountTitleController::class, 'index']);

    Route::post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
});

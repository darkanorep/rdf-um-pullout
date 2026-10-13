<?php

use Illuminate\Support\Facades\Route;

Route::post('login', [\App\Http\Controllers\Controllers\AuthController::class, 'login']);
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::resource('users', \App\Http\Controllers\Controllers\UserController::class);
    Route::resource('roles', \App\Http\Controllers\Controllers\RoleController::class);
    Route::resource('items', \App\Http\Controllers\Controllers\ItemController::class);

    Route::post('logout', [\App\Http\Controllers\Controllers\AuthController::class, 'logout']);
});

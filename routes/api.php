<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::resource('users', \App\Http\Controllers\UserController::class);
    Route::resource('roles', \App\Http\Controllers\RoleController::class);

    Route::post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
});

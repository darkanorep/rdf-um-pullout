<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::resource('users', \App\Http\Controllers\UserController::class);

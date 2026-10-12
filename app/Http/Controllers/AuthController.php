<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(AuthRequest $request) {

        $credentials = $request->only('username', 'password');
        return $this->authService->login($credentials);
    }

    public function logout() {
        return $this->authService->logout();
    }
}

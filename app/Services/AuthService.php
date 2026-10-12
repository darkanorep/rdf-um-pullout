<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class AuthService
{
    public function login(array $credentials): JsonResponse
    {
        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            $user->load([
                'roles',
            ]);
            $token = $user->createToken('auth_token')->plainTextToken;

            $permissions = $user->roles->permissions;

            $userData = $user->toArray();

            // Get first role name as string
            $userData['role'] = $user->roles->first()?->name ?? null;
            $userData['permissions'] = $permissions;
//            $userData['charge'] = $user->charge;
            unset($userData['roles']);


            $cookie = cookie('sanctum', $token);

            return response()->json([
                'message' => 'Login successful',
                'access_token' => $token,
                'user' => $userData,
            ])->withCookie($cookie);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function logout(): JsonResponse {

        auth()->user()->tokens()->delete();

        $cookie = cookie('sanctum', '', -1);

        return response()->json([
            'message' => 'Logout successful',
        ])->withCookie($cookie);
    }
}

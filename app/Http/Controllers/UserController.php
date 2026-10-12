<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;
    public function __construct(private readonly UserService $userService) {}

    public function store(UserRequest $request) {
        $data = $request->validated();
        $user = $this->userService->storeUser($data);

        return $this->responseCreated(
            message: 'User created successfully.',
            data: new UserResource($user)
        );
    }

    public function index()
    {
        $users = $this->userService->getAllUsers();

        if (empty($users->items())) {
            return $this->responseNotFound(message: 'No users found.');
        }

        $users->through(fn ($user) => UserResource::make($user)->resolve());
        return response()->json($users);
    }


    public function show(int $id)
    {
        $user = $this->userService->findById($id);

        if (! $user) {
            return $this->responseNotFound(message: 'User not found.');
        }

        return $this->responseSuccess(
            message: 'User retrieved successfully.',
            data: new UserResource($user)
        );
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

        $updatedUser = $this->userService->updateUser($data, $user);

        return $this->responseAccepted(
            message: 'User updated successfully.',
            data: new UserResource($updatedUser)
        );
    }

    public function destroy(int $id)
    {
        $this->userService->destroyUser($id);

        return $this->responseAccepted(
            message: 'User status updated successfully.'
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RoleService $roleService) {}

    public function store(RoleRequest $request) {
        $data = $request->validated();
        $role = $this->roleService->storeRole($data);

        return $this->responseCreated(
            message: 'Role created successfully.',
            data: new RoleResource($role)
        );
    }

    public function index()
    {
        $users = $this->roleService->getAllRoles();

        if (empty($users->items())) {
            return $this->responseNotFound(message: 'No roles found.');
        }

        $users->through(fn ($role) => RoleResource::make($role)->resolve());
        return response()->json($users);
    }


    public function show(int $id)
    {
        $role = $this->roleService->findById($id);

        if (! $role) {
            return $this->responseNotFound(message: 'Role not found.');
        }

        return $this->responseSuccess(
            message: 'Role retrieved successfully.',
            data: new RoleResource($role)
        );
    }

    public function update(RoleRequest $request, Role $role)
    {
        $data = $request->validated();

        $updatedUser = $this->roleService->updateRole($data, $role);

        return $this->responseAccepted(
            message: 'Role updated successfully.',
            data: new RoleResource($updatedUser)
        );
    }

    public function destroy(int $id)
    {
        $this->roleService->destroyRole($id);

        return $this->responseAccepted(
            message: 'Role status updated successfully.'
        );
    }
}

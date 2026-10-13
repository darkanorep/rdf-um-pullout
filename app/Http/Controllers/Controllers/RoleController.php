<?php

namespace App\Http\Controllers\Controllers;

use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function store(RoleRequest $request) {
        $data = $request->validated();
        $role = $this->roleService->storeRole($data);

        return $this->responseCreated(
            message: 'Role created successfully.',
            data: new RoleResource($role)
        );
    }

    public function index() {
        $roles = $this->roleService->getAllRoles();

        if ($roles->isEmpty()) {
            return $this->responseNotFound(message: 'No roles found.');
        }

        return $roles instanceof LengthAwarePaginator
            ? $roles->through(fn($item) => new RoleResource($item))
            : $this->responseSuccess('Roles retrieved successfully.', RoleResource::collection($roles));
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

        $updatedRole = $this->roleService->updateRole($data, $role);

        return $this->responseAccepted(
            message: 'Role updated successfully.',
            data: new RoleResource($updatedRole)
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

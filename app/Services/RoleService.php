<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
    public function __construct(private readonly Role $role) {}

    public function getAllRoles(int $perPage = 15) : LengthAwarePaginator
    {
        return $this->role->query()->paginate($perPage);
    }

    public function storeRole(array $data): Role
    {
        return $this->role->create($data);
    }

    public function findById(int $id): ?Role
    {
        return $this->role->find($id);
    }

    public function updateRole(array $data, Role $role): Role {
        $role->update($data);
        return $role;
    }

    public function destroyRole(int $id): void
    {
        $role = $this->role->withTrashed()->find($id);
        if ($role->trashed()) {
            $role->restore();
        } else {
            $role->delete();
        }
    }
}

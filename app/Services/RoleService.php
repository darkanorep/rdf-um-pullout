<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
    public function __construct(private readonly Role $role) {}

    public function getAllRoles() 
    {
        return $this->role->query()->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeRole(array $data): Raole
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

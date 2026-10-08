<?php

namespace App\Services;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly User $user) {}

    public function getAllUsers(int $perPage = 15) : LengthAwarePaginator
    {
        return $this->user->query()->paginate($perPage);
    }

    public function storeUser(array $data): User
    {
        $data['password'] = Hash::make($data['username']); // Set the password to the hashed username
        return $this->user->create($data);
    }

    public function findById(int $id): ?User
    {
        return $this->user->find($id);
    }

    public function updateUser(array $data, User $user): User {
        $user->update($data);
        return $user;
    }

    public function destroyUser(int $id): void {

        $user = $this->user->withTrashed()->find($id);
        if ($user->trashed()) {
            $user->restore();
        } else {
            $user->delete();
        }
    }
}

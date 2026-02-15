<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function findById(string $id): ?User
    {
        return User::find($id);
    }

    public function updatePassword(User $user, string $newPasswordHash): bool
    {
        $user->password = $newPasswordHash;
        return $user->save();
    }
}

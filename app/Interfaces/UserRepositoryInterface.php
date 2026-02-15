<?php

namespace App\Interfaces;

use App\Models\User;

interface UserRepositoryInterface
{
    public function create(array $data): User;
    public function findByEmail(string $email): ?User;
    public function findByUsername(string $username): ?User;
    public function findById(string $id): ?User;
    public function updatePassword(User $user, string $newPasswordHash): bool;
}

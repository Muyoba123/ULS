<?php

namespace App\Services;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Exception;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function createUser(array $data): User
    {
        // Password hashing is handled by the User model's 'hashed' cast

        return $this->userRepository->create($data);
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new Exception("User not found.");
        }

        if (!Hash::check($currentPassword, $user->password)) {
            throw new Exception("Current password does not match.");
        }

        return $this->userRepository->updatePassword($user, Hash::make($newPassword));
    }

    public function findUserByCredentials(string $identifier): ?User
    {
        // Identifier can be email or username
        $user = $this->userRepository->findByEmail($identifier);

        if (!$user) {
            $user = $this->userRepository->findByUsername($identifier);
        }

        return $user;
    }
}

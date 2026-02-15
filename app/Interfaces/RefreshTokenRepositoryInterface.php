<?php

namespace App\Interfaces;

use App\Models\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function create(array $data): RefreshToken;
    public function findByToken(string $token): ?RefreshToken;
    public function revoke(RefreshToken $token): bool;
    public function revokeAllForUser(string $userId): void;
    public function findValid(string $token): ?RefreshToken;
}

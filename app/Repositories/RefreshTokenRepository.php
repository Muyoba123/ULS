<?php

namespace App\Repositories;

use App\Interfaces\RefreshTokenRepositoryInterface;
use App\Models\RefreshToken;
use Carbon\Carbon;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function create(array $data): RefreshToken
    {
        return RefreshToken::create($data);
    }

    public function findByToken(string $token): ?RefreshToken
    {
        // We store the hash, so we need to hash the input token to find it
        // BUT wait, the repository should receive the raw token? 
        // No, the Service should hash it. But findByToken usually implies looking up by the stored value.
        // Let's assume the Service handles hashing before calling find? 
        // Or better, let the Repository handle the lookup logic.
        // Actually, for security, we search by the hash.
        // Let's assume the input here is the *hash* or we hash it here?
        // To be clear: Helper methods should probably take the hash if the db column is token_hash.
        // I'll assume the caller passes the hash or I'll implement findByHash.
        // Let's stick to strict naming: findByTokenHash.
        // But the interface says findByToken. I'll implementation it as findByTokenHash effectively.

        return RefreshToken::where('token_hash', $token)->first();
    }

    public function findValid(string $tokenHash): ?RefreshToken
    {
        return RefreshToken::where('token_hash', $tokenHash)
            ->where('revoked', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    public function revoke(RefreshToken $token): bool
    {
        $token->revoked = true;
        return $token->save();
    }

    public function revokeAllForUser(string $userId): void
    {
        RefreshToken::where('user_id', $userId)
            ->update(['revoked' => true]);
    }
}

<?php

namespace App\Services;

use App\Interfaces\RefreshTokenRepositoryInterface;
use App\Services\UserService; // Use concrete service or interface? Concrete service has findUserByCredentials
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str; // For random string
use Carbon\Carbon;
use Exception;

class AuthService
{
    private $refreshTokenRepository;
    private $userService;

    // Config
    private $jwtSecret;
    private $jwtAlgo;
    private $accessTokenTtl;
    private $refreshTokenTtl;

    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        UserService $userService
        )
    {
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->userService = $userService;

        $this->jwtSecret = env('JWT_SECRET');
        $this->jwtAlgo = env('JWT_ALGO', 'HS256');
        $this->accessTokenTtl = (int)env('JWT_ACCESS_TTL', 900);
        $this->refreshTokenTtl = (int)env('JWT_REFRESH_TTL', 2592000);
    }

    public function login(string $identifier, string $password): array
    {
        $user = $this->userService->findUserByCredentials($identifier);

        if (!$user) {
            throw new Exception("Invalid credentials");
        }

        // Verify password - wait, UserService has logic to verify? 
        // No, UserService has changePassword which checks.
        // We need to check password here. UserService doesn't expose a checker.
        // I should use Hash facade here or add a verifyPassword method to UserService.
        // Using Hash facade here is standard.

        if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            throw new Exception("Invalid credentials");
        }

        return $this->generateTokens($user);
    }

    public function refresh(string $refreshToken): array
    {
        // 1. Hash the incoming token to look it up
        $hashedToken = hash('sha256', $refreshToken);

        // 2. Find valid token
        $storedToken = $this->refreshTokenRepository->findValid($hashedToken);

        if (!$storedToken) {
            // Check if it was revoked (Reuse Detection)?
            // If we find a revoked token, we might want to revoke all tokens for that user (Security Best Practice)
            // But for now, "Minimal" spec just says "Validate token: exists, not expired, not revoked".
            throw new Exception("Invalid or expired refresh token");
        }

        // 3. Revoke the used token (Rotation)
        $this->refreshTokenRepository->revoke($storedToken);

        // 4. Generate new tokens
        $user = $storedToken->user; // Access via relationship
        return $this->generateTokens($user);
    }

    public function logout(string $refreshToken): void
    {
        $hashedToken = hash('sha256', $refreshToken);
        $storedToken = $this->refreshTokenRepository->findByToken($hashedToken);

        if ($storedToken) {
            $this->refreshTokenRepository->revoke($storedToken);
        }
    }

    private function generateTokens(User $user): array
    {
        // 1. Access Token (JWT)
        $issuedAt = time();
        $expiration = $issuedAt + $this->accessTokenTtl;

        $payload = [
            'sub' => $user->id,
            'iss' => 'ULS',
            'iat' => $issuedAt,
            'exp' => $expiration,
        ];

        $accessToken = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgo);

        // 2. Refresh Token (Opaque)
        $rawRefreshToken = Str::random(64);
        $hashedRefreshToken = hash('sha256', $rawRefreshToken);

        $this->refreshTokenRepository->create([
            'user_id' => $user->id,
            'token_hash' => $hashedRefreshToken,
            'expires_at' => Carbon::now()->addSeconds($this->refreshTokenTtl),
            'revoked' => false
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
            'expires_in' => $this->accessTokenTtl
        ];
    }
}

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
        // Rate Limiting
        $key = 'login_attempts:' . $identifier . ':' . request()->ip();

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);
            throw new Exception("Too many login attempts. Please try again in " . ceil($seconds / 60) . " minutes.");
        }

        $user = $this->userService->findUserByCredentials($identifier);

        if (!$user || !\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($key, 600); // 10 minutes (600 seconds)
            throw new Exception("Invalid credentials");
        }

        \Illuminate\Support\Facades\RateLimiter::clear($key);

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

<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Exception;

class AuthController extends Controller
{
    private $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $tokens = $this->authService->login(
                $request->input('identifier'),
                $request->input('password')
            );

            // Set refresh token in HTTP-only cookie
            $cookie = $this->createRefreshTokenCookie($tokens['refresh_token']);
            
            // Remove refresh token from response body
            unset($tokens['refresh_token']);

            return response()->json($tokens)->cookie($cookie);
        }
        catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        // Read refresh token from cookie instead of request body
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['error' => 'Refresh token not found'], 401);
        }

        try {
            $tokens = $this->authService->refresh($refreshToken);
            
            // Set new refresh token in HTTP-only cookie
            $cookie = $this->createRefreshTokenCookie($tokens['refresh_token']);
            
            // Remove refresh token from response body
            unset($tokens['refresh_token']);

            return response()->json($tokens)->cookie($cookie);
        }
        catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        // Read refresh token from cookie
        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            $this->authService->logout($refreshToken);
        }

        // Clear the refresh token cookie
        $cookie = cookie()->forget('refresh_token');

        return response()->json(['message' => 'Logged out successfully'])->cookie($cookie);
    }

    /**
     * Create a refresh token cookie with security attributes
     */
    private function createRefreshTokenCookie(string $refreshToken)
    {
        $ttl = (int)env('JWT_REFRESH_TTL', 2592000); // 30 days in seconds
        $minutes = $ttl / 60; // Convert to minutes for Laravel cookie

        return cookie(
            'refresh_token',           // name
            $refreshToken,             // value
            $minutes,                  // minutes
            '/api/auth',               // path
            env('COOKIE_DOMAIN'),      // domain
            env('COOKIE_SECURE', false), // secure (HTTPS only)
            true,                      // httpOnly
            false,                     // raw
            env('COOKIE_SAME_SITE', 'lax') // sameSite
        );
    }
}

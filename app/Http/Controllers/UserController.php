<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Exception;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function create(Request $request): JsonResponse
    {
        // Simple validation - in real app use FormRequest or $request->validate()
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'username' => 'nullable|string|unique:users,username',
            'password' => 'required|min:8',
        ]);

        try {
            $user = $this->userService->createUser($data);
            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'created_at' => $user->created_at,
            ], 201);
        }
        catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid', // In a real app, this would come from the auth token
            'current_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        try {
            // Note: In a real app, we'd get the user ID from the authenticated user, not the request body
            // But the prompt implies an endpoint for this.
            // "8.5 Change Password - POST /users/change-password"

            $this->userService->changePassword(
                $request->input('user_id'),
                $request->input('current_password'),
                $request->input('new_password')
            );

            return response()->json(['message' => 'Password changed successfully']);
        }
        catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

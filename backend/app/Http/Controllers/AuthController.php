<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        Log::info('Registration attempt started', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'email' => $request->input('email'),
            'name' => $request->input('name')
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Log::info('Registration data validated', ['validated_data' => $data]);

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            Log::info('User created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name
            ]);

            // Create Sanctum token
            $token = $user->createToken('api-token')->plainTextToken;

            Log::info('Sanctum token created', [
                'user_id' => $user->id,
                'token_preview' => substr($token, 0, 10) . '...'
            ]);

            return response()->json([
                'user' => $user,
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->input('email')
            ]);
            
            throw $e;
        }
    }

    public function login(Request $request)
    {
        Log::info('Login attempt started', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'email' => $request->input('email')
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        Log::info('Login credentials validated', ['email' => $credentials['email']]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            Log::warning('Login failed - invalid credentials', [
                'email' => $credentials['email'],
                'user_found' => $user ? true : false,
                'ip' => $request->ip()
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Log::info('Login successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'ip' => $request->ip()
        ]);

        try {
            // Create Sanctum token
            $token = $user->createToken('api-token')->plainTextToken;

            Log::info('Login token created', [
                'user_id' => $user->id,
                'token_preview' => substr($token, 0, 10) . '...'
            ]);

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            Log::error('Login token creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function logout(Request $request)
    {
        Log::info('Logout attempt', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip()
        ]);

        try {
            $request->user()?->currentAccessToken()?->delete();
            
            Log::info('Logout successful', [
                'user_id' => $request->user()?->id
            ]);

            return response()->noContent();

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);
            
            throw $e;
        }
    }
}


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
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user  = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            Log::info('auth.registered', [
                'operation' => 'register',
                'status'    => 'success',
                'user_id'   => $user->id,
                'email'     => $user->email,
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'user'  => $user,
                'token' => $token,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('auth.register_failed', [
                'operation'   => 'register',
                'status'      => 'failed',
                'email'       => $request->input('email'),
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'ip'          => $request->ip(),
            ]);

            throw $e;
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            Log::warning('auth.login_failed', [
                'operation'  => 'login',
                'status'     => 'invalid_credentials',
                'email'      => $credentials['email'],
                'user_found' => (bool) $user,
                'ip'         => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        try {
            $token = $user->createToken('api-token')->plainTextToken;

            Log::info('auth.login_success', [
                'operation' => 'login',
                'status'    => 'success',
                'user_id'   => $user->id,
                'email'     => $user->email,
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'user'  => $user,
                'token' => $token,
            ]);

        } catch (\Throwable $e) {
            Log::error('auth.login_token_failed', [
                'operation'   => 'login',
                'status'      => 'token_creation_failed',
                'user_id'     => $user->id,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function logout(Request $request)
    {
        $userId = $request->user()?->id;

        try {
            $request->user()?->currentAccessToken()?->delete();

            Log::info('auth.logout', [
                'operation' => 'logout',
                'status'    => 'success',
                'user_id'   => $userId,
                'ip'        => $request->ip(),
            ]);

            return response()->noContent();

        } catch (\Throwable $e) {
            Log::error('auth.logout_failed', [
                'operation'   => 'logout',
                'status'      => 'failed',
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }
}

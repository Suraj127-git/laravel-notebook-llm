<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($validated);

        return response()->json($request->user()->fresh());
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }

    public function usage(Request $request)
    {
        $logs = AiUsageLog::where('user_id', $request->user()->id)
            ->selectRaw('provider, model, operation, SUM(prompt_tokens) as tokens_in, SUM(completion_tokens) as tokens_out, SUM(estimated_cost) as cost_usd, COUNT(*) as requests, strftime("%Y-%m", created_at) as month')
            ->groupByRaw('provider, model, operation, strftime("%Y-%m", created_at)')
            ->orderByDesc('month')
            ->get();

        return response()->json($logs);
    }
}

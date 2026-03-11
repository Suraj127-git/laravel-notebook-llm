<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        try {
            $request->user()->update($validated);

            Log::info('user.profile_updated', [
                'operation'      => 'update_profile',
                'status'         => 'success',
                'user_id'        => $request->user()->id,
                'changed_fields' => array_keys($validated),
            ]);

            return response()->json($request->user()->fresh());

        } catch (\Throwable $e) {
            Log::error('user.profile_update_failed', [
                'operation'   => 'update_profile',
                'status'      => 'failed',
                'user_id'     => $request->user()->id,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            Log::warning('user.password_update_wrong_current', [
                'operation' => 'update_password',
                'status'    => 'wrong_current_password',
                'user_id'   => $user->id,
            ]);

            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        try {
            $user->update(['password' => Hash::make($request->password)]);

            Log::info('user.password_updated', [
                'operation' => 'update_password',
                'status'    => 'success',
                'user_id'   => $user->id,
            ]);

            return response()->json(['message' => 'Password updated.']);

        } catch (\Throwable $e) {
            Log::error('user.password_update_failed', [
                'operation'   => 'update_password',
                'status'      => 'failed',
                'user_id'     => $user->id,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function destroy(Request $request)
    {
        $user   = $request->user();
        $userId = $user->id;

        try {
            $user->currentAccessToken()->delete();
            $user->delete();

            Log::info('user.account_deleted', [
                'operation' => 'delete_account',
                'status'    => 'success',
                'user_id'   => $userId,
                'ip'        => $request->ip(),
            ]);

            return response()->json(['message' => 'Account deleted.']);

        } catch (\Throwable $e) {
            Log::error('user.account_delete_failed', [
                'operation'   => 'delete_account',
                'status'      => 'failed',
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function usage(Request $request)
    {
        $driver = DB::connection()->getDriverName();

        $monthExpr = match ($driver) {
            'pgsql'  => "TO_CHAR(created_at, 'YYYY-MM')",
            'mysql'  => "DATE_FORMAT(created_at, '%Y-%m')",
            default  => "strftime('%Y-%m', created_at)",
        };

        $logs = AiUsageLog::where('user_id', $request->user()->id)
            ->selectRaw("provider, model, operation, SUM(prompt_tokens) as tokens_in, SUM(completion_tokens) as tokens_out, SUM(estimated_cost) as cost_usd, COUNT(*) as requests, {$monthExpr} as month")
            ->groupByRaw("provider, model, operation, {$monthExpr}")
            ->orderByDesc('month')
            ->get();

        Log::debug('user.usage_fetched', [
            'operation'   => 'fetch_usage',
            'user_id'     => $request->user()->id,
            'record_count' => $logs->count(),
        ]);

        return response()->json($logs);
    }
}

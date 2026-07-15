<?php

namespace App\Http\Controllers;

use App\Models\LoginSuccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LoginSuccessLogController extends Controller
{
    private function success($message, $data = null, int $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    private function failed($message, $errors = null, int $code = 400)
    {
        return response()->json([
            'status' => 'failed',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * GET /reports/login-success
     * Filters: user_id, user_type, login_type, start_date, end_date, per_page
     */
    public function report(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => ['nullable', 'integer'],
                'user_type' => ['nullable', 'string', 'max:50'],
                'login_type' => ['nullable', 'string', 'max:50'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $query = LoginSuccessLog::with('user');

            if (!empty($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }

            if (!empty($validated['user_type'])) {
                $query->where('user_type', $validated['user_type']);
            }

            if (!empty($validated['login_type'])) {
                $query->where('login_type', $validated['login_type']);
            }

            if (!empty($validated['start_date'])) {
                $query->where('logged_in_at', '>=', Carbon::parse($validated['start_date'])->startOfDay());
            }

            if (!empty($validated['end_date'])) {
                $query->where('logged_in_at', '<=', Carbon::parse($validated['end_date'])->endOfDay());
            }

            $perPage = (int) ($validated['per_page'] ?? 20);
            $logs = $query->latest('logged_in_at')->paginate($perPage);

            return $this->success('Login success report fetched successfully', $logs);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
}

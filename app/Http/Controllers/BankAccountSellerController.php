<?php

namespace App\Http\Controllers;

use App\Models\BankAccountSeller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountSellerController extends Controller
{
    /**
     * Add a bank account for a user
     */
    public function addBankAccount(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'bank_name' => 'required|string|max:255',
                'account_name' => 'required|string|max:255',
                'account_no' => 'required|string|max:255',
                'type' => 'required|string|max:100',
                'address' => 'nullable|string|max:1000',
                'route' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->failed('Validation failed', $validator->errors(), 422);
            }

            $account = BankAccountSeller::create($validator->validated());

            return $this->success('Bank account added successfully', $account, 201);
        } catch (\Exception $e) {
            return $this->failed('Could not add bank account', $e->getMessage(), 500);
        }
    }

    /**
     * Get all bank accounts for a user
     */
    public function getAccountByUserId($userId)
    {
        try {
            $accounts = BankAccountSeller::where('user_id', $userId)->get();

            return $this->success('Bank accounts retrieved', $accounts);
        } catch (\Exception $e) {
            return $this->failed('Could not retrieve bank accounts', $e->getMessage(), 500);
        }
    }

    // Response helpers
    private function success($message, $data = null, int $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    private function failed($message, $errors = null, int $code = 400)
    {
        return response()->json([
            'status' => 'failed',
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ShippingCost;
use Illuminate\Http\Request;

class ShippingCostController extends Controller
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
     * POST /shipping-costs/set
     */
    public function setShippingCost(Request $request)
    {
        try {
            $validated = $request->validate([
                'shipping_cost' => ['nullable', 'numeric', 'min:0'],
                'is_shop_wise' => ['nullable', 'boolean'],
                'is_distance_wise' => ['nullable', 'boolean'],
                'is_product_wise' => ['nullable', 'boolean'],
                'per_shop_cost' => ['nullable', 'numeric', 'min:0'],
                'status' => ['nullable', 'string', 'max:50'],
            ]);

            foreach (['is_shop_wise', 'is_distance_wise', 'is_product_wise'] as $flag) {
                if (array_key_exists($flag, $validated)) {
                    $validated[$flag] = (bool) $validated[$flag];
                }
            }

            $shippingCost = ShippingCost::first();

            if (!$shippingCost) {
                $shippingCost = ShippingCost::create($validated);
            } else {
                $shippingCost->fill($validated);
                $shippingCost->save();
            }

            return $this->success('Shipping cost saved successfully', $shippingCost);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /shipping-costs
     */
    public function getShippingCost()
    {
        try {
            $shippingCost = ShippingCost::first();

            if (!$shippingCost) {
                return $this->failed('Shipping cost not found', null, 404);
            }

            return $this->success('Shipping cost fetched successfully', $shippingCost);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
}

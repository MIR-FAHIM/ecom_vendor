<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Shops;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Transaction;
use Carbon\Carbon;

class ReportController extends Controller
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
            'errors' => $errors
        ], $code);
    }

    /**
     * GET /reports/dashboard
     * Returns summary counts and sales metrics
     */
    public function dashboard(Request $request)
    {
        try {
            $productsCount = Product::count();
            $shopsCount = Shops::count();
            // Users use `user_type` (default 'customer') in the users table
            $customersCount = User::where('user_type', 'customer')->count();

            $ordersCount = Order::count();
            $activeCarts = Cart::where('status', 'active')->count();

            // Sales sums (only consider completed credit transactions)
            $totalSell = (float) Transaction::where('trx_type', 'credit')
                ->where('status', 'completed')
                ->sum('amount');

            $today = Carbon::today()->toDateString();
            $yesterday = Carbon::yesterday()->toDateString();

            $todaySell = (float) Transaction::where('trx_type', 'credit')
                ->where('status', 'completed')
                ->whereDate('created_at', $today)
                ->sum('amount');

            $yesterdaySell = (float) Transaction::where('trx_type', 'credit')
                ->where('status', 'completed')
                ->whereDate('created_at', $yesterday)
                ->sum('amount');

            $last7Start = Carbon::today()->subDays(6)->toDateString(); // include today = 7 days
            $last7Sell = (float) Transaction::where('trx_type', 'credit')
                ->where('status', 'completed')
                ->whereDate('created_at', '>=', $last7Start)
                ->sum('amount');

            // Daily breakdown for last 7 days (most recent first)
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $d = Carbon::today()->subDays($i)->toDateString();
                $sum = (float) Transaction::where('trx_type', 'credit')
                    ->where('status', 'completed')
                    ->whereDate('created_at', $d)
                    ->sum('amount');
                $days[] = [
                    'date' => $d,
                    'total' => $sum,
                ];
            }

            $data = [
                'products_count' => $productsCount,
                'shops_count' => $shopsCount,
                'customers_count' => $customersCount,
                'orders_count' => $ordersCount,
                'active_carts' => $activeCarts,
                'total_sell' => $totalSell,
                'today_sell' => $todaySell,
                'yesterday_sell' => $yesterdaySell,
                'last_7_days_sell' => $last7Sell,
                'last_7_days_breakdown' => $days,
            ];

            return $this->success('Dashboard metrics fetched', $data);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /reports/shop/{userId}
     * Returns shop metrics for a vendor user
     */
    public function shopReportByUser($userId)
    {
        try {
            $shopIds = Shops::where('user_id', $userId)->pluck('id');

            if ($shopIds->isEmpty()) {
                $data = [
                    'shops_count' => 0,
                    'orders_count' => 0,
                    'orders_amount' => 0,
                    'products_count' => 0,
                ];

                return $this->success('Shop report fetched', $data);
            }

            $shopsCount = $shopIds->count();
            $ordersCount = OrderItem::whereIn('shop_id', $shopIds)
                ->distinct('order_id')
                ->count('order_id');

            $ordersAmount = (float) OrderItem::whereIn('shop_id', $shopIds)
                ->sum('line_total');

            $productsCount = Product::whereIn('shop_id', $shopIds)->count();

            $data = [
                'shops_count' => $shopsCount,
                'orders_count' => $ordersCount,
                'orders_amount' => $ordersAmount,
                'products_count' => $productsCount,
            ];

            return $this->success('Shop report fetched', $data);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /reports/shop/{shopId}/sales
     * Returns last 12 months sales totals for a shop
     */
    public function shopSalesReport($shopId, Request $request)
    {
        try {
            $shop = Shops::find($shopId);
            if (!$shop) {
                return $this->failed('Shop not found', null, 404);
            }

            $end = Carbon::now()->endOfMonth();
            $start = $end->copy()->subMonths(11)->startOfMonth();

            $monthlyTotals = OrderItem::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(line_total) as total")
                ->where('shop_id', $shopId)
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('ym')
                ->orderBy('ym')
                ->pluck('total', 'ym');

            $months = [];
            $cursor = $start->copy();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m');
                $months[] = [
                    'month' => $key,
                    'amount' => (float) ($monthlyTotals[$key] ?? 0),
                ];
                $cursor->addMonth();
            }

            $data = [
                'shop_id' => (int) $shopId,
                'start_month' => $start->format('Y-m'),
                'end_month' => $end->format('Y-m'),
                'months' => $months,
            ];

            return $this->success('Shop sales report fetched', $data);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
}

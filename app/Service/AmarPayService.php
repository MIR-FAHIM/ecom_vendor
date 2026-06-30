<?php

namespace App\Service;

use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class AmarPayService
{
    public function initiatePayment(?int $orderId, ?User $authenticatedUser = null, ?string $paymentGroupId = null): JsonResponse
    {
        $configError = $this->validateConfig();
        if ($configError) {
            return $this->jsonFailed($configError, null, 500);
        }

        if (!$authenticatedUser) {
            return $this->jsonFailed('Authentication required', null, 401);
        }

        $orders = $this->resolvePaymentOrders($orderId, $paymentGroupId);
        if ($orders->isEmpty()) {
            return $this->jsonFailed('No payable orders found', null, 404);
        }

        if (!$this->canInitiatePaymentForOrders($orders, $authenticatedUser)) {
            return $this->jsonFailed('You cannot pay for this order', [
                'order_user_ids' => $orders->pluck('user_id')->unique()->values(),
                'authenticated_user_id' => (int) $authenticatedUser->id,
            ], 403);
        }

        $unpaidOrders = $orders
            ->filter(fn (Order $order) => $order->payment_status !== 'paid')
            ->values();

        if ($unpaidOrders->isEmpty()) {
            return $this->jsonFailed('All orders in this payment are already paid', null, 409);
        }

        $amount = round($unpaidOrders->sum(fn (Order $order) => (float) $order->total), 2);
        if ($amount <= 0) {
            return $this->jsonFailed('Payment total must be greater than zero', null, 422);
        }

        $primaryOrder = $unpaidOrders->first();
        $paymentGroupId = $paymentGroupId ?: $primaryOrder->payment_group_id;
        $orderIds = $unpaidOrders->pluck('id')->values()->all();
        $merchantTransactionId = 'PAY-' . $primaryOrder->id . '-' . now()->format('ymdHis') . '-' . random_int(1000, 9999);

        $payment = OnlinePayment::create([
            'order_id' => $primaryOrder->id,
            'payment_group_id' => $paymentGroupId,
            'order_ids' => $orderIds,
            'user_id' => $primaryOrder->user_id,
            'gateway' => 'aamarpay',
            'merchant_transaction_id' => $merchantTransactionId,
            'amount' => $amount,
            'currency' => 'BDT',
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        $payload = [
            'store_id' => config('services.aamarpay.store_id'),
            'tran_id' => $merchantTransactionId,
            'success_url' => $this->callbackUrl('success'),
            'fail_url' => $this->callbackUrl('fail'),
            'cancel_url' => $this->callbackUrl('cancel'),
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'signature_key' => config('services.aamarpay.signature_key'),
            'desc' => $unpaidOrders->count() > 1
                ? 'Checkout payment ' . ($paymentGroupId ?? $merchantTransactionId)
                : 'Order #' . ($primaryOrder->order_number ?? $primaryOrder->id),
            'cus_name' => $primaryOrder->customer_name ?: 'Customer',
            'cus_email' => optional($primaryOrder->user)->email ?: 'customer@example.com',
            'cus_phone' => $primaryOrder->customer_phone ?: optional($primaryOrder->user)->phone ?: '01000000000',
            'cus_add1' => $primaryOrder->shipping_address ?: 'Not provided',
            'cus_city' => $primaryOrder->district ?: $primaryOrder->area ?: 'Dhaka',
            'cus_state' => $primaryOrder->zone ?: $primaryOrder->district ?: 'Dhaka',
            'cus_country' => 'Bangladesh',
            'opt_a' => $paymentGroupId,
            'opt_b' => implode(',', $orderIds),
            'opt_c' => (string) $unpaidOrders->count(),
            'type' => 'json',
        ];
        $payload = array_filter($payload, fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::timeout(20)
                ->asJson()
                ->post($this->paymentUrl(), $payload);
        } catch (ConnectionException $e) {
            $payment->update([
                'status' => 'failed',
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            return $this->jsonFailed('Could not connect to AamarPay', null, 502);
        }

        $result = $response->json();
        if (!is_array($result)) {
            $result = ['raw_response' => $response->body()];
        }

        $payment->update([
            'gateway_response' => [
                'request' => $this->safePayloadForLogs($payload),
                'response' => $result,
            ],
        ]);

        if (!$response->successful() || !$this->gatewayAccepted($result) || empty($result['payment_url'])) {
            $payment->update(['status' => 'failed']);

            return $this->jsonFailed('AamarPay rejected the payment request', $result, 502);
        }

        $payment->update(['status' => 'pending']);

        return $this->jsonSuccess('Payment initiated successfully', [
            'payment_id' => $payment->id,
            'payment_group_id' => $payment->payment_group_id,
            'order_ids' => $payment->order_ids,
            'amount' => $payment->amount,
            'merchant_transaction_id' => $payment->merchant_transaction_id,
            'payment_url' => $result['payment_url'],
        ]);
    }

    public function success(array $data): JsonResponse
    {
        $payment = $this->findPaymentFromCallback($data);
        if (!$payment) {
            return $this->jsonFailed('Payment not found for callback', null, 404);
        }

        $validation = $this->validateGatewayPayment($data, $payment);
        if (!$validation['valid']) {
            $payment->update([
                'gateway_response' => $this->appendCallbackData($payment, $data, $validation),
            ]);

            return $this->jsonFailed('Payment could not be verified with AamarPay', $validation, 422);
        }

        $payment = DB::transaction(function () use ($payment, $data, $validation) {
            $lockedPayment = OnlinePayment::whereKey($payment->id)->lockForUpdate()->first();

            if ($lockedPayment->status !== 'success') {
                $orderIds = $this->orderIdsForPayment($lockedPayment);
                $orders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();
                $gatewayTransactionId = $data['pg_txnid'] ?? $lockedPayment->gateway_transaction_id;

                $lockedPayment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $gatewayTransactionId,
                    'gateway_fee' => $data['gateway_fee'] ?? $lockedPayment->gateway_fee ?? 0,
                    'gateway_response' => $this->appendCallbackData($lockedPayment, $data, $validation),
                    'paid_at' => $lockedPayment->paid_at ?: now(),
                ]);

                Order::whereIn('id', $orderIds)->update(['payment_status' => 'paid']);

                foreach ($orders as $order) {
                    Transaction::firstOrCreate(
                        [
                            'order_id' => $order->id,
                            'source' => 'online_payment',
                            'type' => 'order_payment',
                        ],
                        [
                            'amount' => $order->total,
                            'ref_id' => $lockedPayment->merchant_transaction_id,
                            'trx_id' => $gatewayTransactionId,
                            'trx_type' => 'credit',
                            'status' => 'completed',
                            'note' => 'Online payment received for order #' . $order->order_number,
                        ]
                    );
                }
            }

            $freshPayment = $lockedPayment->fresh(['order']);
            $freshPayment->paid_orders = Order::whereIn('id', $this->orderIdsForPayment($lockedPayment))->get();

            return $freshPayment;
        });

        return $this->jsonSuccess('Payment verified successfully', [
            'payment' => $payment,
        ]);
    }

    public function fail(array $data): JsonResponse
    {
        return $this->markCallbackAs($data, 'failed', 'Payment marked as failed');
    }

    public function cancel(array $data): JsonResponse
    {
        return $this->markCallbackAs($data, 'cancelled', 'Payment marked as cancelled');
    }

    private function markCallbackAs(array $data, string $status, string $message): JsonResponse
    {
        $payment = $this->findPaymentFromCallback($data);
        if (!$payment) {
            return $this->jsonFailed('Payment not found for callback', null, 404);
        }

        if ($payment->status === 'success') {
            return $this->jsonSuccess('Payment is already verified successfully', ['payment_id' => $payment->id]);
        }

        $payment->update([
            'status' => $status,
            'gateway_response' => $this->appendCallbackData($payment, $data),
        ]);

        if ($status === 'failed') {
            Order::whereIn('id', $this->orderIdsForPayment($payment))
                ->where('payment_status', '!=', 'paid')
                ->update(['payment_status' => 'failed']);
        }

        return $this->jsonSuccess($message, ['payment_id' => $payment->id]);
    }

    private function resolvePaymentOrders(?int $orderId, ?string $paymentGroupId): Collection
    {
        if ($paymentGroupId) {
            return Order::where('payment_group_id', $paymentGroupId)
                ->orderBy('id')
                ->get();
        }

        if (!$orderId) {
            return collect();
        }

        $order = Order::find($orderId);
        if (!$order) {
            return collect();
        }

        if ($order->payment_group_id) {
            return Order::where('payment_group_id', $order->payment_group_id)
                ->orderBy('id')
                ->get();
        }

        return collect([$order]);
    }

    private function canInitiatePaymentForOrders(Collection $orders, User $authenticatedUser): bool
    {
        if ($this->isAdmin($authenticatedUser)) {
            return true;
        }

        return $orders->every(
            fn (Order $order) => (int) $order->user_id === (int) $authenticatedUser->id
        );
    }

    private function isAdmin(User $authenticatedUser): bool
    {
        $role = strtolower((string) ($authenticatedUser->role ?? ''));
        $userType = strtolower((string) ($authenticatedUser->user_type ?? ''));

        return in_array('admin', [$role, $userType], true);
    }

    private function orderIdsForPayment(OnlinePayment $payment): array
    {
        if (is_array($payment->order_ids) && count($payment->order_ids) > 0) {
            return array_map('intval', $payment->order_ids);
        }

        if ($payment->payment_group_id) {
            return Order::where('payment_group_id', $payment->payment_group_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [(int) $payment->order_id];
    }

    private function findPaymentFromCallback(array $data): ?OnlinePayment
    {
        $merchantTransactionId = $data['mer_txnid'] ?? $data['tran_id'] ?? null;
        if (!$merchantTransactionId) {
            return null;
        }

        return OnlinePayment::with('order')
            ->where('merchant_transaction_id', $merchantTransactionId)
            ->first();
    }

    private function validateGatewayPayment(array $data, OnlinePayment $payment): array
    {
        $requestId = $data['pg_txnid'] ?? $data['bank_txn'] ?? $data['mer_txnid'] ?? null;
        if (!$requestId) {
            return ['valid' => false, 'reason' => 'Missing gateway transaction id'];
        }

        try {
            $response = Http::timeout(20)->get($this->validationUrl(), [
                'request_id' => $requestId,
                'store_id' => config('services.aamarpay.store_id'),
                'signature_key' => config('services.aamarpay.signature_key'),
                'type' => 'json',
            ]);
        } catch (ConnectionException $e) {
            return ['valid' => false, 'reason' => 'Could not connect to AamarPay validation API'];
        }

        $body = $response->json();
        if (!is_array($body)) {
            $body = ['raw_response' => $response->body()];
        }

        $amount = (float) ($body['amount'] ?? $body['amount_bdt'] ?? $data['amount'] ?? 0);
        $status = strtolower((string) ($body['pay_status'] ?? $body['status'] ?? $data['pay_status'] ?? ''));
        $merchantTransactionId = $body['mer_txnid'] ?? $body['tran_id'] ?? $data['mer_txnid'] ?? null;

        $valid = $response->successful()
            && in_array($status, ['successful', 'success', 'paid', 'complete', 'completed'], true)
            && $merchantTransactionId === $payment->merchant_transaction_id
            && abs($amount - (float) $payment->amount) < 0.01;

        return [
            'valid' => $valid,
            'status' => $status,
            'amount' => $amount,
            'gateway_response' => $body,
        ];
    }

    private function appendCallbackData(OnlinePayment $payment, array $callback, array $validation = []): array
    {
        $existing = is_array($payment->gateway_response) ? $payment->gateway_response : [];

        return array_merge($existing, [
            'callback' => $callback,
            'validation' => $validation,
        ]);
    }

    private function safePayloadForLogs(array $payload): array
    {
        $safePayload = $payload;
        $safePayload['signature_key'] = '***';

        return $safePayload;
    }

    private function gatewayAccepted(array $result): bool
    {
        return in_array($result['result'] ?? null, [true, 'true', 'TRUE', 1, '1'], true);
    }

    private function validateConfig(): ?string
    {
        if (!config('services.aamarpay.base_url')) {
            return 'AamarPay base URL is not configured';
        }

        if (!config('services.aamarpay.store_id') || !config('services.aamarpay.signature_key')) {
            return 'AamarPay credentials are not configured';
        }

        return null;
    }

    private function paymentUrl(): string
    {
        return rtrim(config('services.aamarpay.base_url'), '/') . '/jsonpost.php';
    }

    private function validationUrl(): string
    {
        return config('services.aamarpay.validation_url')
            ?: rtrim(config('services.aamarpay.base_url'), '/') . '/api/v1/trxcheck/request.php';
    }

    private function callbackUrl(string $type): string
    {
        return config("services.aamarpay.{$type}_url")
            ?: url("/api/payments/aamarpay/{$type}");
    }

    private function jsonSuccess(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    private function jsonFailed(string $message, mixed $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => 'failed',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}

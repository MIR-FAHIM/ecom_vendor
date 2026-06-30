<?php

namespace App\Service;

use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AmarPayService
{
    public function initiatePayment(int $orderId, ?int $authenticatedUserId = null): JsonResponse
    {
        $configError = $this->validateConfig();
        if ($configError) {
            return $this->jsonFailed($configError, null, 500);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return $this->jsonFailed('Order not found', null, 404);
        }

        if ($authenticatedUserId && (int) $order->user_id !== $authenticatedUserId) {
            return $this->jsonFailed('You cannot pay for this order', null, 403);
        }

        if ($order->payment_status === 'paid') {
            return $this->jsonFailed('This order is already paid', null, 409);
        }

        if ((float) $order->total <= 0) {
            return $this->jsonFailed('Order total must be greater than zero', null, 422);
        }

        $merchantTransactionId = 'PAY-' . $order->id . '-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);

        $payment = OnlinePayment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => 'aamarpay',
            'merchant_transaction_id' => $merchantTransactionId,
            'amount' => $order->total,
            'currency' => 'BDT',
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        $payload = [
            'store_id' => config('services.aamarpay.store_id'),
            'signature_key' => config('services.aamarpay.signature_key'),
            'tran_id' => $merchantTransactionId,
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => 'BDT',
            'desc' => 'Order #' . ($order->order_number ?? $order->id),
            'cus_name' => $order->customer_name ?: 'Customer',
            'cus_email' => optional($order->user)->email ?: 'customer@example.com',
            'cus_phone' => $order->customer_phone ?: optional($order->user)->phone ?: '01000000000',
            'success_url' => $this->callbackUrl('success'),
            'fail_url' => $this->callbackUrl('fail'),
            'cancel_url' => $this->callbackUrl('cancel'),
            'type' => 'json',
        ];

        try {
            $response = Http::timeout(20)
                ->asForm()
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

        $payment->update(['gateway_response' => $result]);

        if (!$response->successful() || !$this->gatewayAccepted($result) || empty($result['payment_url'])) {
            $payment->update(['status' => 'failed']);

            return $this->jsonFailed('AamarPay rejected the payment request', $result, 502);
        }

        $payment->update(['status' => 'pending']);

        return $this->jsonSuccess('Payment initiated successfully', [
            'payment_id' => $payment->id,
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
                $lockedPayment->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $data['pg_txnid'] ?? $lockedPayment->gateway_transaction_id,
                    'gateway_fee' => $data['gateway_fee'] ?? $lockedPayment->gateway_fee ?? 0,
                    'gateway_response' => $this->appendCallbackData($lockedPayment, $data, $validation),
                    'paid_at' => $lockedPayment->paid_at ?: now(),
                ]);

                $lockedPayment->order()->update(['payment_status' => 'paid']);

                Transaction::firstOrCreate(
                    [
                        'order_id' => $lockedPayment->order_id,
                        'source' => 'online_payment',
                        'type' => 'order_payment',
                    ],
                    [
                        'amount' => $lockedPayment->amount,
                        'ref_id' => $lockedPayment->merchant_transaction_id,
                        'trx_id' => $lockedPayment->gateway_transaction_id,
                        'trx_type' => 'credit',
                        'status' => 'completed',
                        'note' => 'Online payment received for order #' . optional($lockedPayment->order)->order_number,
                    ]
                );
            }

            return $lockedPayment->fresh(['order']);
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
            $payment->order()->update(['payment_status' => 'failed']);
        }

        return $this->jsonSuccess($message, ['payment_id' => $payment->id]);
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

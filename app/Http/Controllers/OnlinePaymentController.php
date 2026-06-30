<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Service\AmarPayService;

class OnlinePaymentController extends Controller
{
    public function __construct(
        protected AmarPayService $aamarPayService
    ) {}

    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id', 'required_without:payment_group_id'],
            'payment_group_id' => ['nullable', 'string', 'max:64', 'required_without:order_id'],
        ]);

        $user = $request->attributes->get('api_user');

        return $this->aamarPayService->initiatePayment(
            isset($validated['order_id']) ? (int) $validated['order_id'] : null,
            $user,
            $validated['payment_group_id'] ?? null
        );
    }

    public function success(Request $request)
    {
        return $this->aamarPayService->success($request->all());
    }

    public function fail(Request $request)
    {
        return $this->aamarPayService->fail($request->all());
    }

    public function cancel(Request $request)
    {
        return $this->aamarPayService->cancel($request->all());
    }
}

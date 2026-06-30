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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $user = $request->attributes->get('api_user');

        return $this->aamarPayService->initiatePayment(
            (int) $validated['order_id'],
            $user ? (int) $user->id : null
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

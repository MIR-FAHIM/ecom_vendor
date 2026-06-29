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
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        return $this->aamarPayService->initiatePayment($request->order_id, $request->user_id);
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

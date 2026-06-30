<?php

namespace App\Service;

use App\Models\Order;
use App\Models\OnlinePayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AmarPayService
{
    public function initiatePayment($orderId, $userId)
    {
        
    try{
        $order = Order::findOrFail($orderId);

        $merchantTransaction = 'PAY-'.$order->id.'-'.time();


        
        $payment = OnlinePayment::create([
            'order_id' => $order->id,
            'user_id' => $userId,
            'gateway' => 'aamarpay',
            'merchant_transaction_id' => $merchantTransaction,
            'amount' => $order->total,
            'currency' => 'BDT',
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        $response = Http::post(config('services.aamarpay.base_url').'/jsonpost.php', [

            'store_id' => config('services.aamarpay.store_id'),

            'signature_key' => config('services.aamarpay.signature_key'),

            'tran_id' => $merchantTransaction,

            'amount' => $order->total,

            'currency' => 'BDT',

            'desc' => 'Order #'.$order->id,

            'cus_name' => $order->name,

            'cus_email' => $order->email,

            'cus_phone' => $order->phone,

            'success_url' => 'https://resellerbrain.com/admin-panel/support-tickets',

            'fail_url' =>'https://resellerbrain.com/admin-panel/support-tickets',

            'cancel_url' => 'https://resellerbrain.com/admin-panel/support-tickets',

            'type' => 'json'
        ]);

        $result = $response->json();

        $payment->gateway_response = $result;
        $payment->save();

        if (($result['result'] ?? null) != true) {

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ],400);
        }

        return response()->json([
            'success'=>true,
            'payment_url'=>$result['payment_url']
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
           return response()->json([
                'success' => false,
                'message' => $e->errors()
            ],422);
         
        } catch (\Throwable $e) {
           return response()->json([
                'success' => false,
                'message' =>$e->getMessage()
            ],421);
           
        }

    }

    public function success(array $data)
    {
        $payment = OnlinePayment::where(
            'merchant_transaction_id',
            $data['mer_txnid']
        )->firstOrFail();

        $payment->update([
            'status'=>'success',
            'gateway_transaction_id'=>$data['pg_txnid'],
            'gateway_fee'=>$data['gateway_fee'] ?? 0,
            'gateway_response'=>$data,
            'paid_at'=>now(),
        ]);

        $payment->order()->update([
            'payment_status'=>'paid'
        ]);

        return response()->json([
            'success'=>true
        ]);
    }

    public function fail(array $data)
    {
        $payment = OnlinePayment::where(
            'merchant_transaction_id',
            $data['mer_txnid']
        )->first();

        if($payment){

            $payment->update([
                'status'=>'failed',
                'gateway_response'=>$data,
            ]);

            $payment->order()->update([
                'payment_status'=>'failed'
            ]);
        }

        return response()->json([
            'success'=>false
        ]);
    }

    public function cancel(array $data)
    {
        $payment = OnlinePayment::where(
            'merchant_transaction_id',
            $data['mer_txnid']
        )->first();

        if($payment){

            $payment->update([
                'status'=>'cancelled',
                'gateway_response'=>$data,
            ]);
        }

        return response()->json([
            'success'=>false
        ]);
    }
}
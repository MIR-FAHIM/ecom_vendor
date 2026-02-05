<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SMSController extends Controller
{
	/**
	 * Send SMS via Muthobarta API
	 */
	public function sendSms(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'sender_id' => 'required|string|max:50',
			'receiver' => 'required|string|max:20',
			'message' => 'required|string|max:1000',
			'remove_duplicate' => 'sometimes|boolean',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Validation failed',
				'errors' => $validator->errors(),
			], 422);
		}

		$apiKey = config('services.muthobarta.api_key');
		$baseUrl = rtrim(config('services.muthobarta.base_url'), '/');

		if (! $apiKey) {
			return response()->json([
				'status' => 'failed',
				'message' => 'SMS API key not configured',
			], 500);
		}

		try {
			$payload = $validator->validated();

			$response = Http::timeout(15)
				->withHeaders([
					'Authorization' => $apiKey,
					'Accept' => 'application/json',
				])
				->post($baseUrl . '/send-sms', $payload);

			if ($response->failed()) {
				return response()->json([
					'status' => 'failed',
					'message' => 'SMS provider request failed',
					'errors' => $response->json() ?? $response->body(),
				], $response->status());
			}

			return response()->json([
				'status' => 'success',
				'message' => 'SMS sent successfully',
				'data' => $response->json(),
			]);
		} catch (\Exception $e) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Could not send SMS',
				'errors' => $e->getMessage(),
			], 500);
		}
	}
}

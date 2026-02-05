<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\OTPSms;
use Illuminate\Support\Carbon;

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
			'type' => 'nullable|string|max:50',
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
			$otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
			$payload['message'] = "Your login OTP is {$otp}.";

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

			OTPSms::create([
				'mobile_number' => $payload['receiver'],
				'otp' => $otp,
				'is_expired' => false,
				'status' => true,
				'validity_time' => 5,
				'type' => $payload['type'] ?? 'login',
			]);

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

	/**
	 * Verify OTP
	 */
	public function verifyOtp(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'mobile_number' => 'required|string|max:20',
			'otp' => 'required|string|max:10',
			'type' => 'nullable|string|max:50',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Validation failed',
				'errors' => $validator->errors(),
			], 422);
		}

		try {
			$query = OTPSms::where('mobile_number', $request->mobile_number)
				->where('otp', $request->otp)
				->where('is_expired', false)
				->where('status', true);

			if ($request->filled('type')) {
				$query->where('type', $request->type);
			}

			$otpRecord = $query->latest()->first();

			if (! $otpRecord) {
				return response()->json([
					'status' => 'failed',
					'message' => 'Invalid OTP',
				], 400);
			}

			$expiresAt = $otpRecord->created_at
				? Carbon::parse($otpRecord->created_at)->addMinutes($otpRecord->validity_time)
				: Carbon::now()->subMinute();

			if (Carbon::now()->greaterThan($expiresAt)) {
				$otpRecord->update([
					'is_expired' => true,
					'status' => false,
				]);

				return response()->json([
					'status' => 'failed',
					'message' => 'OTP expired',
				], 400);
			}

			$otpRecord->update([
				'is_expired' => true,
				'status' => false,
			]);

			return response()->json([
				'status' => 'success',
				'message' => 'OTP verified successfully',
			]);
		} catch (\Exception $e) {
			return response()->json([
				'status' => 'failed',
				'message' => 'Could not verify OTP',
				'errors' => $e->getMessage(),
			], 500);
		}
	}
}
